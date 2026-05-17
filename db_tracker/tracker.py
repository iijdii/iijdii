"""
Deutsche Bahn ticket price tracker.

Usage:
  # One-time check
  python tracker.py check --from "Berlin Hbf" --to "München Hbf" --date 2026-06-15

  # Search for station IDs
  python tracker.py search "Köln"

  # Show price history for a route
  python tracker.py history --from "Berlin Hbf" --to "München Hbf" --date 2026-06-15

  # Run as daemon (check every N minutes)
  python tracker.py watch --from "Berlin Hbf" --to "München Hbf" --date 2026-06-15 --interval 30
"""
import argparse
import sys
import time
from datetime import datetime

import db_api
import storage
import notifier

PRICE_CHANGE_THRESHOLD_EUR = 0.01  # treat changes smaller than this as noise


def resolve_station(query: str) -> tuple[str, str]:
    """Resolve station name to (id, canonical_name). Exits on failure."""
    results = db_api.search_station(query)
    if not results:
        sys.exit(f"Станция не найдена: '{query}'")
    if len(results) == 1:
        return results[0]["id"], results[0]["name"]
    # Try exact match first
    for r in results:
        if r["name"].lower() == query.lower():
            return r["id"], r["name"]
    # Print options and take first
    print(f"Найдено несколько вариантов для '{query}':")
    for r in results:
        print(f"  {r['id']}  {r['name']}")
    print(f"Используется: {results[0]['name']} ({results[0]['id']})")
    return results[0]["id"], results[0]["name"]


def cmd_search(args: argparse.Namespace) -> None:
    results = db_api.search_station(args.query)
    if not results:
        print("Ничего не найдено.")
        return
    print(f"{'ID':<20} {'Название'}")
    print("-" * 50)
    for r in results:
        print(f"{r['id']:<20} {r['name']}")


def _do_check(from_id: str, from_name: str, to_id: str, to_name: str,
              date: str, seat_class: int, silent: bool = False) -> None:
    route_label = f"{from_name} → {to_name} ({date}, {seat_class}. кл.)"
    if not silent:
        print(f"\n[{datetime.now():%H:%M:%S}] Проверка: {route_label}")

    journeys = db_api.fetch_journeys(from_id, to_id, date, seat_class)
    if not journeys:
        print("  Рейсы не найдены или API недоступен.")
        return

    route_id = storage.upsert_route(from_id, from_name, to_id, to_name, date, seat_class)

    for j in journeys:
        dep = j["departure"]
        price = j["price_eur"]
        old_price = storage.get_last_price(route_id, dep)

        storage.save_snapshot(
            route_id=route_id,
            departure=dep,
            arrival=j["arrival"],
            duration_min=j["duration_min"],
            price_eur=price,
            offer_name=j["offer_name"],
            is_available=True,
        )

        if old_price is None:
            # First time seeing this departure
            notifier.notify_new_route(route_label, dep[:16], price)
        elif price is not None and abs(price - old_price) >= PRICE_CHANGE_THRESHOLD_EUR:
            storage.save_alert(route_id, dep, old_price, price)
            notifier.notify_price_change(route_label, dep[:16], old_price, price)
        else:
            if not silent:
                price_str = f"{price:.2f} €" if price is not None else "—"
                print(f"  {dep[:16]}  {price_str}  (без изменений)")


def cmd_check(args: argparse.Namespace) -> None:
    from_id, from_name = resolve_station(args.from_station)
    to_id, to_name = resolve_station(args.to_station)
    storage.init_db()
    _do_check(from_id, from_name, to_id, to_name, args.date, args.seat_class)


def cmd_history(args: argparse.Namespace) -> None:
    from_id, from_name = resolve_station(args.from_station)
    to_id, to_name = resolve_station(args.to_station)
    storage.init_db()
    route_id = storage.upsert_route(from_id, from_name, to_id, to_name, args.date, args.seat_class)
    # Get all distinct departures
    import sqlite3
    with storage.get_connection() as conn:
        deps = conn.execute(
            "SELECT DISTINCT departure FROM price_snapshots WHERE route_id=? ORDER BY departure",
            (route_id,),
        ).fetchall()
    if not deps:
        print("История пуста — сначала выполните 'check'.")
        return
    for row in deps:
        dep = row["departure"]
        history = storage.get_price_history(route_id, dep)
        print(f"\n  Отправление: {dep[:16]}")
        for h in history:
            price_str = f"{h['price_eur']:.2f} €" if h["price_eur"] is not None else "—"
            print(f"    {h['checked_at'][:19]}  {price_str}  {h['offer_name'] or ''}")


def cmd_watch(args: argparse.Namespace) -> None:
    from_id, from_name = resolve_station(args.from_station)
    to_id, to_name = resolve_station(args.to_station)
    storage.init_db()
    interval_sec = args.interval * 60
    print(f"Мониторинг: {from_name} → {to_name} | дата {args.date} | "
          f"интервал {args.interval} мин. | Ctrl+C для остановки")
    while True:
        try:
            _do_check(from_id, from_name, to_id, to_name, args.date, args.seat_class, silent=True)
        except Exception as exc:
            print(f"[{datetime.now():%H:%M:%S}] Ошибка: {exc}")
        time.sleep(interval_sec)


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Deutsche Bahn ticket price tracker",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    sub = parser.add_subparsers(dest="command", required=True)

    # search
    p_search = sub.add_parser("search", help="Поиск станции по названию")
    p_search.add_argument("query", help="Название станции")

    # shared args for check/history/watch
    route_args = argparse.ArgumentParser(add_help=False)
    route_args.add_argument("--from", dest="from_station", required=True, metavar="STATION")
    route_args.add_argument("--to", dest="to_station", required=True, metavar="STATION")
    route_args.add_argument("--date", required=True, help="YYYY-MM-DD")
    route_args.add_argument("--class", dest="seat_class", type=int, choices=[1, 2], default=2)

    # check
    sub.add_parser("check", parents=[route_args], help="Однократная проверка цен")

    # history
    sub.add_parser("history", parents=[route_args], help="История цен для маршрута")

    # watch
    p_watch = sub.add_parser("watch", parents=[route_args], help="Непрерывный мониторинг")
    p_watch.add_argument("--interval", type=int, default=30, metavar="MIN",
                         help="Интервал проверки в минутах (по умолчанию: 30)")

    return parser


if __name__ == "__main__":
    parser = build_parser()
    args = parser.parse_args()
    dispatch = {"search": cmd_search, "check": cmd_check,
                "history": cmd_history, "watch": cmd_watch}
    dispatch[args.command](args)
