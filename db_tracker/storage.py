import sqlite3
import os
from datetime import datetime
from typing import Optional

DB_PATH = os.path.join(os.path.dirname(__file__), "prices.db")


def get_connection() -> sqlite3.Connection:
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


def init_db() -> None:
    with get_connection() as conn:
        conn.executescript("""
            CREATE TABLE IF NOT EXISTS routes (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                from_id     TEXT NOT NULL,
                from_name   TEXT NOT NULL,
                to_id       TEXT NOT NULL,
                to_name     TEXT NOT NULL,
                travel_date TEXT NOT NULL,
                class       INTEGER NOT NULL DEFAULT 2,
                UNIQUE (from_id, to_id, travel_date, class)
            );

            CREATE TABLE IF NOT EXISTS price_snapshots (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                route_id        INTEGER NOT NULL REFERENCES routes(id),
                checked_at      TEXT NOT NULL,
                departure       TEXT NOT NULL,
                arrival         TEXT NOT NULL,
                duration_min    INTEGER,
                price_eur       REAL,
                offer_name      TEXT,
                is_available    INTEGER NOT NULL DEFAULT 1
            );

            CREATE TABLE IF NOT EXISTS price_alerts (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                route_id        INTEGER NOT NULL REFERENCES routes(id),
                detected_at     TEXT NOT NULL,
                departure       TEXT NOT NULL,
                old_price_eur   REAL,
                new_price_eur   REAL,
                change_eur      REAL,
                change_pct      REAL
            );
        """)


def upsert_route(from_id: str, from_name: str, to_id: str, to_name: str,
                 travel_date: str, seat_class: int = 2) -> int:
    with get_connection() as conn:
        conn.execute(
            """INSERT OR IGNORE INTO routes
               (from_id, from_name, to_id, to_name, travel_date, class)
               VALUES (?, ?, ?, ?, ?, ?)""",
            (from_id, from_name, to_id, to_name, travel_date, seat_class),
        )
        row = conn.execute(
            "SELECT id FROM routes WHERE from_id=? AND to_id=? AND travel_date=? AND class=?",
            (from_id, to_id, travel_date, seat_class),
        ).fetchone()
        return row["id"]


def save_snapshot(route_id: int, departure: str, arrival: str,
                  duration_min: Optional[int], price_eur: Optional[float],
                  offer_name: Optional[str], is_available: bool) -> None:
    with get_connection() as conn:
        conn.execute(
            """INSERT INTO price_snapshots
               (route_id, checked_at, departure, arrival, duration_min,
                price_eur, offer_name, is_available)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
            (route_id, datetime.utcnow().isoformat(),
             departure, arrival, duration_min,
             price_eur, offer_name, int(is_available)),
        )


def get_last_price(route_id: int, departure: str) -> Optional[float]:
    with get_connection() as conn:
        row = conn.execute(
            """SELECT price_eur FROM price_snapshots
               WHERE route_id=? AND departure=? AND is_available=1
               ORDER BY checked_at DESC LIMIT 1""",
            (route_id, departure),
        ).fetchone()
        return row["price_eur"] if row else None


def save_alert(route_id: int, departure: str,
               old_price: Optional[float], new_price: Optional[float]) -> None:
    if old_price is None or new_price is None:
        return
    change = new_price - old_price
    change_pct = (change / old_price * 100) if old_price else 0.0
    with get_connection() as conn:
        conn.execute(
            """INSERT INTO price_alerts
               (route_id, detected_at, departure,
                old_price_eur, new_price_eur, change_eur, change_pct)
               VALUES (?, ?, ?, ?, ?, ?, ?)""",
            (route_id, datetime.utcnow().isoformat(), departure,
             old_price, new_price, change, change_pct),
        )


def get_price_history(route_id: int, departure: str) -> list:
    with get_connection() as conn:
        rows = conn.execute(
            """SELECT checked_at, price_eur, offer_name FROM price_snapshots
               WHERE route_id=? AND departure=? AND is_available=1
               ORDER BY checked_at""",
            (route_id, departure),
        ).fetchall()
        return [dict(r) for r in rows]


def get_all_routes() -> list:
    with get_connection() as conn:
        rows = conn.execute("SELECT * FROM routes").fetchall()
        return [dict(r) for r in rows]
