package com.dbtracker

import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import java.net.URLEncoder
import java.time.LocalDate
import java.time.LocalTime
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter
import java.util.concurrent.TimeUnit

data class Journey(
    val departure: String,
    val arrival: String,
    val durationMin: Int,
    val priceEur: Double?,
    val offerName: String?,
    val trainName: String?,
    val changes: Int
)

data class Station(val id: String, val name: String) {
    override fun toString() = name
}

object ApiClient {

    private val client = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(20, TimeUnit.SECONDS)
        .build()

    private const val BASE = "https://v6.db.transport.rest"

    val popularStations = listOf(
        Station("8000096", "Stuttgart Hbf"),
        Station("8011160", "Berlin Hbf"),
        Station("8000261", "München Hbf"),
        Station("8000105", "Frankfurt(Main)Hbf"),
        Station("8002549", "Hamburg Hbf"),
        Station("8000207", "Köln Hbf"),
        Station("8000085", "Düsseldorf Hbf"),
        Station("8000152", "Hannover Hbf"),
        Station("8010205", "Leipzig Hbf"),
        Station("8010085", "Dresden Hbf"),
        Station("8000284", "Nürnberg Hbf"),
        Station("8000068", "Dortmund Hbf"),
        Station("8000050", "Bremen Hbf"),
        Station("8000244", "Mannheim Hbf"),
        Station("8000031", "Augsburg Hbf"),
    )

    fun fetchJourneys(
        fromId: String,
        toId: String,
        date: LocalDate,
        fromTime: LocalTime,
        iceOnly: Boolean
    ): List<Journey> {
        val depIso = "${date}T${fromTime.format(DateTimeFormatter.ofPattern("HH:mm"))}:00+02:00"
        val url = buildString {
            append("$BASE/journeys")
            append("?from=$fromId&to=$toId")
            append("&departure=${URLEncoder.encode(depIso, "UTF-8")}")
            append("&results=20&stopovers=false&tickets=true")
            if (iceOnly) {
                append("&nationalExpress=true&national=false&regionalExpress=false")
                append("&regional=false&suburban=false&bus=false&ferry=false")
                append("&subway=false&tram=false&taxi=false")
            } else {
                append("&nationalExpress=true&national=true&regionalExpress=true")
                append("&regional=true&suburban=false&bus=false&ferry=false")
                append("&subway=false&tram=false&taxi=false")
            }
        }

        val request = Request.Builder()
            .url(url)
            .header("Accept", "application/json")
            .header("User-Agent", "DBTracker-Android/1.0")
            .build()

        val body = client.newCall(request).execute().use { resp ->
            if (!resp.isSuccessful) throw Exception("HTTP ${resp.code}")
            resp.body?.string() ?: return emptyList()
        }

        val journeys = JSONObject(body).getJSONArray("journeys")
        val result = mutableListOf<Journey>()

        for (i in 0 until journeys.length()) {
            val j = journeys.getJSONObject(i)
            val legs = j.getJSONArray("legs")
            if (legs.length() == 0) continue

            val firstLeg = legs.getJSONObject(0)
            val lastLeg = legs.getJSONObject(legs.length() - 1)

            val dep = firstLeg.optString("departure").ifEmpty {
                firstLeg.optString("plannedDeparture")
            }
            val arr = lastLeg.optString("arrival").ifEmpty {
                lastLeg.optString("plannedArrival")
            }
            if (dep.isEmpty() || arr.isEmpty()) continue

            val line = firstLeg.optJSONObject("line")
            val trainName = line?.optString("name")?.ifEmpty { null }

            // ICE filter: skip non-ICE trains
            if (iceOnly && trainName != null && !trainName.startsWith("ICE")) continue

            val priceObj = j.optJSONObject("price")
            val priceEur = priceObj?.let {
                val amount = it.optDouble("amount", -1.0)
                if (amount >= 0) amount / 100.0 else null
            }
            val offerName = priceObj?.optString("name")?.ifEmpty { null }

            val durationMin = try {
                val d = OffsetDateTime.parse(dep)
                val a = OffsetDateTime.parse(arr)
                ((a.toEpochSecond() - d.toEpochSecond()) / 60).toInt()
            } catch (_: Exception) { 0 }

            result.add(
                Journey(
                    departure = dep,
                    arrival = arr,
                    durationMin = durationMin,
                    priceEur = priceEur,
                    offerName = offerName,
                    trainName = trainName,
                    changes = legs.length() - 1
                )
            )
        }
        return result
    }
}
