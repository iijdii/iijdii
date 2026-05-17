"""
Wrapper around the unofficial DB Transport REST API (v6.db.transport.rest).
Docs: https://v6.db.transport.rest/

Set env var DB_TRACKER_MOCK=1 to use built-in mock data (for local testing).
"""
import os
import requests
from datetime import datetime
from typing import Optional

BASE_URL = "https://v6.db.transport.rest"
SESSION = requests.Session()
SESSION.headers.update({"Accept": "application/json", "User-Agent": "db-price-tracker/1.0"})
TIMEOUT = 15

_MOCK_STATIONS = [
    {"id": "8011160", "name": "Berlin Hbf"},
    {"id": "8000261", "name": "München Hbf"},
    {"id": "8000105", "name": "Frankfurt(Main)Hbf"},
    {"id": "8000207", "name": "Köln Hbf"},
    {"id": "8000096", "name": "Hamburg Hbf"},
]

_MOCK_JOURNEYS = [
    {"departure": "2026-06-15T06:00:00+02:00", "arrival": "2026-06-15T11:55:00+02:00",
     "duration_min": 355, "price_eur": 29.90, "offer_name": "Sparpreis"},
    {"departure": "2026-06-15T09:00:00+02:00", "arrival": "2026-06-15T14:52:00+02:00",
     "duration_min": 352, "price_eur": 49.90, "offer_name": "Sparpreis"},
    {"departure": "2026-06-15T12:00:00+02:00", "arrival": "2026-06-15T17:55:00+02:00",
     "duration_min": 355, "price_eur": 39.90, "offer_name": "Sparpreis"},
    {"departure": "2026-06-15T16:00:00+02:00", "arrival": "2026-06-15T21:58:00+02:00",
     "duration_min": 358, "price_eur": 59.90, "offer_name": "Flexpreis"},
]


def _use_mock() -> bool:
    return os.getenv("DB_TRACKER_MOCK", "").strip() == "1"


def search_station(query: str) -> list[dict]:
    """Return list of {id, name} matching the query string."""
    if _use_mock():
        q = query.lower()
        return [s for s in _MOCK_STATIONS if q in s["name"].lower()]
    resp = SESSION.get(f"{BASE_URL}/stops", params={"query": query, "results": 5}, timeout=TIMEOUT)
    resp.raise_for_status()
    data = resp.json()
    stops = data if isinstance(data, list) else data.get("stops", [])
    return [{"id": s["id"], "name": s["name"]} for s in stops if s.get("id") and s.get("name")]


def _parse_price(journey: dict) -> tuple[Optional[float], Optional[str]]:
    """Extract lowest price (€) and offer name from a journey dict."""
    price_obj = journey.get("price")
    if price_obj and price_obj.get("amount") is not None:
        return round(price_obj["amount"] / 100, 2), price_obj.get("name")

    fare_summary = journey.get("faresSummary")
    if fare_summary:
        cheapest = fare_summary.get("cheapestPrice")
        if cheapest and cheapest.get("amount") is not None:
            return round(cheapest["amount"] / 100, 2), cheapest.get("name")

    return None, None


def _duration_minutes(dep: str, arr: str) -> Optional[int]:
    try:
        fmt = "%Y-%m-%dT%H:%M:%S%z"
        d = datetime.fromisoformat(dep)
        a = datetime.fromisoformat(arr)
        return int((a - d).total_seconds() / 60)
    except Exception:
        return None


def fetch_journeys(from_id: str, to_id: str, date: str,
                   seat_class: int = 2,
                   _mock_price_delta: float = 0.0) -> list[dict]:
    """
    Fetch journeys for a given date (YYYY-MM-DD).
    Returns list of dicts with departure, arrival, duration_min, price_eur, offer_name.
    """
    # DB API uses ISO 8601 datetime; request from 00:00 of the given day
    departure_dt = f"{date}T00:00:00+02:00"
    params = {
        "from": from_id,
        "to": to_id,
        "departure": departure_dt,
        "results": 20,
        "nationalExpress": "true",
        "national": "true",
        "regionalExpress": "true",
        "regional": "true",
        "bus": "false",
        "ferry": "false",
        "subway": "false",
        "tram": "false",
        "taxi": "false",
        "stopovers": "false",
        "tickets": "true",
    }
    if seat_class == 1:
        params["firstClass"] = "true"

    if _use_mock():
        import copy
        journeys = copy.deepcopy(_MOCK_JOURNEYS)
        for j in journeys:
            if j["price_eur"] is not None:
                j["price_eur"] = round(j["price_eur"] + _mock_price_delta, 2)
        return journeys

    resp = SESSION.get(f"{BASE_URL}/journeys", params=params, timeout=TIMEOUT)
    resp.raise_for_status()
    journeys = resp.json().get("journeys", [])

    results = []
    for j in journeys:
        legs = j.get("legs", [])
        if not legs:
            continue
        dep = legs[0].get("departure") or legs[0].get("plannedDeparture", "")
        arr = legs[-1].get("arrival") or legs[-1].get("plannedArrival", "")
        price, offer = _parse_price(j)
        results.append({
            "departure": dep,
            "arrival": arr,
            "duration_min": _duration_minutes(dep, arr),
            "price_eur": price,
            "offer_name": offer,
        })

    return results
