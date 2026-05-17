"""Tests for the DB price tracker (uses mock mode — no real network required)."""
import os
import sys
import tempfile
import unittest

os.environ["DB_TRACKER_MOCK"] = "1"

sys.path.insert(0, os.path.dirname(__file__))
import db_api
import storage
import notifier


class TestDbApi(unittest.TestCase):
    def test_search_station_match(self):
        results = db_api.search_station("Berlin")
        self.assertTrue(any(r["name"] == "Berlin Hbf" for r in results))

    def test_search_station_no_match(self):
        results = db_api.search_station("XYZ_NONEXISTENT_999")
        self.assertEqual(results, [])

    def test_fetch_journeys_returns_list(self):
        journeys = db_api.fetch_journeys("8011160", "8000261", "2026-06-15")
        self.assertIsInstance(journeys, list)
        self.assertGreater(len(journeys), 0)

    def test_fetch_journeys_fields(self):
        journeys = db_api.fetch_journeys("8011160", "8000261", "2026-06-15")
        for j in journeys:
            self.assertIn("departure", j)
            self.assertIn("arrival", j)
            self.assertIn("price_eur", j)
            self.assertIn("duration_min", j)

    def test_fetch_journeys_price_delta(self):
        base = db_api.fetch_journeys("8011160", "8000261", "2026-06-15")
        delta = db_api.fetch_journeys("8011160", "8000261", "2026-06-15", _mock_price_delta=5.0)
        self.assertAlmostEqual(delta[0]["price_eur"] - base[0]["price_eur"], 5.0, places=2)


class TestStorage(unittest.TestCase):
    def setUp(self):
        self._tmp = tempfile.NamedTemporaryFile(suffix=".db", delete=False)
        self._tmp.close()
        storage.DB_PATH = self._tmp.name
        storage.init_db()

    def tearDown(self):
        os.unlink(self._tmp.name)

    def test_upsert_route_idempotent(self):
        id1 = storage.upsert_route("A", "Alpha", "B", "Beta", "2026-06-15")
        id2 = storage.upsert_route("A", "Alpha", "B", "Beta", "2026-06-15")
        self.assertEqual(id1, id2)

    def test_save_and_get_snapshot(self):
        route_id = storage.upsert_route("A", "Alpha", "B", "Beta", "2026-06-15")
        storage.save_snapshot(route_id, "2026-06-15T06:00:00+02:00", "2026-06-15T12:00:00+02:00",
                              360, 29.90, "Sparpreis", True)
        price = storage.get_last_price(route_id, "2026-06-15T06:00:00+02:00")
        self.assertAlmostEqual(price, 29.90)

    def test_get_last_price_returns_none_for_unknown(self):
        route_id = storage.upsert_route("A", "Alpha", "B", "Beta", "2026-06-15")
        price = storage.get_last_price(route_id, "2026-06-15T99:00:00+02:00")
        self.assertIsNone(price)

    def test_save_alert(self):
        route_id = storage.upsert_route("A", "Alpha", "B", "Beta", "2026-06-15")
        storage.save_alert(route_id, "2026-06-15T06:00:00+02:00", 29.90, 24.90)
        with storage.get_connection() as conn:
            row = conn.execute("SELECT * FROM price_alerts WHERE route_id=?", (route_id,)).fetchone()
        self.assertIsNotNone(row)
        self.assertAlmostEqual(row["change_eur"], -5.0, places=2)

    def test_price_history(self):
        route_id = storage.upsert_route("A", "Alpha", "B", "Beta", "2026-06-15")
        dep = "2026-06-15T06:00:00+02:00"
        storage.save_snapshot(route_id, dep, "2026-06-15T12:00:00+02:00", 360, 29.90, "Sparpreis", True)
        storage.save_snapshot(route_id, dep, "2026-06-15T12:00:00+02:00", 360, 24.90, "Sparpreis", True)
        history = storage.get_price_history(route_id, dep)
        self.assertEqual(len(history), 2)
        self.assertAlmostEqual(history[0]["price_eur"], 29.90)
        self.assertAlmostEqual(history[1]["price_eur"], 24.90)

    def test_get_all_routes(self):
        storage.upsert_route("A", "Alpha", "B", "Beta", "2026-06-15")
        storage.upsert_route("C", "Gamma", "D", "Delta", "2026-07-01")
        routes = storage.get_all_routes()
        self.assertEqual(len(routes), 2)


class TestPriceChangeDetection(unittest.TestCase):
    """Integration test: full check cycle with price change detection."""

    def setUp(self):
        self._tmp = tempfile.NamedTemporaryFile(suffix=".db", delete=False)
        self._tmp.close()
        storage.DB_PATH = self._tmp.name
        storage.init_db()
        self.alerts: list[dict] = []

    def tearDown(self):
        os.unlink(self._tmp.name)

    def _run_check(self, price_delta: float = 0.0) -> list[dict]:
        from_id, from_name = "8011160", "Berlin Hbf"
        to_id, to_name = "8000261", "München Hbf"
        date = "2026-06-15"
        journeys = db_api.fetch_journeys(from_id, to_id, date, _mock_price_delta=price_delta)
        route_id = storage.upsert_route(from_id, from_name, to_id, to_name, date, 2)
        alerts = []
        for j in journeys:
            dep = j["departure"]
            old_price = storage.get_last_price(route_id, dep)
            storage.save_snapshot(route_id, dep, j["arrival"], j["duration_min"],
                                  j["price_eur"], j["offer_name"], True)
            if old_price is not None and j["price_eur"] is not None:
                if abs(j["price_eur"] - old_price) >= 0.01:
                    storage.save_alert(route_id, dep, old_price, j["price_eur"])
                    alerts.append({"departure": dep, "old": old_price, "new": j["price_eur"]})
        return alerts

    def test_first_run_no_alerts(self):
        alerts = self._run_check(0.0)
        self.assertEqual(alerts, [])

    def test_price_drop_generates_alert(self):
        self._run_check(0.0)       # baseline
        alerts = self._run_check(-5.0)  # price drops by €5
        self.assertGreater(len(alerts), 0)
        self.assertLess(alerts[0]["new"], alerts[0]["old"])

    def test_price_increase_generates_alert(self):
        self._run_check(0.0)
        alerts = self._run_check(+10.0)
        self.assertGreater(len(alerts), 0)
        self.assertGreater(alerts[0]["new"], alerts[0]["old"])

    def test_no_change_no_alert(self):
        self._run_check(0.0)
        alerts = self._run_check(0.0)
        self.assertEqual(alerts, [])


class TestNotifier(unittest.TestCase):
    def test_notify_price_change_prints(self):
        import io
        from contextlib import redirect_stdout
        f = io.StringIO()
        with redirect_stdout(f):
            notifier.notify_price_change("Berlin → München", "2026-06-15T06:00", 39.90, 29.90)
        out = f.getvalue()
        self.assertIn("29.90", out)
        self.assertIn("39.90", out)
        self.assertIn("▼", out)

    def test_notify_price_increase_prints(self):
        import io
        from contextlib import redirect_stdout
        f = io.StringIO()
        with redirect_stdout(f):
            notifier.notify_price_change("Berlin → München", "2026-06-15T06:00", 29.90, 39.90)
        out = f.getvalue()
        self.assertIn("▲", out)


if __name__ == "__main__":
    unittest.main(verbosity=2)
