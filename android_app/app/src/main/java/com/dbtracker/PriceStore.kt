package com.dbtracker

import android.content.Context
import org.json.JSONArray
import org.json.JSONObject

data class StoredPrice(val departure: String, val priceEur: Double)

data class PriceChange(
    val departure: String,
    val oldPrice: Double,
    val newPrice: Double,
    val trainName: String?
) {
    val delta get() = newPrice - oldPrice
    val deltaPct get() = delta / oldPrice * 100.0
    val isDropped get() = delta < 0
}

object PriceStore {

    private const val PREFS = "db_price_store"
    private const val KEY_MONITOR_ACTIVE = "monitor_active"

    fun routeKey(fromId: String, toId: String, date: String, iceOnly: Boolean) =
        "${fromId}_${toId}_${date}_${if (iceOnly) "ice" else "all"}"

    fun savePrices(ctx: Context, key: String, prices: List<StoredPrice>) {
        val arr = JSONArray()
        prices.forEach { p ->
            arr.put(JSONObject().apply {
                put("dep", p.departure)
                put("price", p.priceEur)
            })
        }
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit().putString("prices_$key", arr.toString()).apply()
    }

    fun loadPrices(ctx: Context, key: String): List<StoredPrice> {
        val raw = ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getString("prices_$key", null) ?: return emptyList()
        return try {
            val arr = JSONArray(raw)
            (0 until arr.length()).map { i ->
                val o = arr.getJSONObject(i)
                StoredPrice(o.getString("dep"), o.getDouble("price"))
            }
        } catch (e: Exception) { emptyList() }
    }

    fun detectChanges(
        stored: List<StoredPrice>,
        current: List<Journey>
    ): List<PriceChange> {
        val storedMap = stored.associateBy { it.departure }
        val changes = mutableListOf<PriceChange>()
        for (j in current) {
            val cur = j.priceEur ?: continue
            val old = storedMap[j.departure] ?: continue
            if (Math.abs(cur - old.priceEur) >= 0.01) {
                changes += PriceChange(j.departure, old.priceEur, cur, j.trainName)
            }
        }
        return changes
    }

    fun setMonitorActive(ctx: Context, routeKey: String, active: Boolean) {
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit().putBoolean("${KEY_MONITOR_ACTIVE}_$routeKey", active).apply()
    }

    fun isMonitorActive(ctx: Context, routeKey: String): Boolean =
        ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getBoolean("${KEY_MONITOR_ACTIVE}_$routeKey", false)
}
