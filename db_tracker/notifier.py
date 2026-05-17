"""
Notification backends. Currently: console + optional Telegram.
Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID env vars to enable Telegram.
"""
import os
import requests
from typing import Optional

_BOT_TOKEN = os.getenv("TELEGRAM_BOT_TOKEN", "")
_CHAT_ID = os.getenv("TELEGRAM_CHAT_ID", "")


def _telegram(text: str) -> None:
    if not (_BOT_TOKEN and _CHAT_ID):
        return
    try:
        requests.post(
            f"https://api.telegram.org/bot{_BOT_TOKEN}/sendMessage",
            json={"chat_id": _CHAT_ID, "text": text, "parse_mode": "HTML"},
            timeout=10,
        )
    except Exception as exc:
        print(f"[notifier] Telegram error: {exc}")


def notify_price_change(route_label: str, departure: str,
                        old_price: Optional[float], new_price: Optional[float]) -> None:
    if old_price is None or new_price is None:
        return
    change = new_price - old_price
    direction = "▼ упала" if change < 0 else "▲ выросла"
    msg = (
        f"💰 Цена {direction}!\n"
        f"Маршрут: {route_label}\n"
        f"Отправление: {departure}\n"
        f"Было: {old_price:.2f} €  →  Стало: {new_price:.2f} € "
        f"({change:+.2f} €, {(change/old_price*100):+.1f}%)"
    )
    print(msg)
    _telegram(msg)


def notify_new_route(route_label: str, departure: str, price: Optional[float]) -> None:
    price_str = f"{price:.2f} €" if price is not None else "цена неизвестна"
    msg = f"🆕 Новый маршрут отслеживается: {route_label}\n{departure} — {price_str}"
    print(msg)
    _telegram(msg)
