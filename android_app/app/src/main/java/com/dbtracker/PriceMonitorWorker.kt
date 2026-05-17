package com.dbtracker

import android.content.Context
import androidx.work.Data
import androidx.work.PeriodicWorkRequest
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.Worker
import androidx.work.WorkerParameters
import java.time.LocalDate
import java.time.LocalTime
import java.util.concurrent.TimeUnit

class PriceMonitorWorker(
    private val ctx: Context,
    params: WorkerParameters
) : Worker(ctx, params) {

    override fun doWork(): Result {
        val fromId   = inputData.getString(KEY_FROM_ID)   ?: return Result.failure()
        val fromName = inputData.getString(KEY_FROM_NAME) ?: return Result.failure()
        val toId     = inputData.getString(KEY_TO_ID)     ?: return Result.failure()
        val toName   = inputData.getString(KEY_TO_NAME)   ?: return Result.failure()
        val date     = inputData.getString(KEY_DATE)      ?: return Result.failure()
        val time     = inputData.getString(KEY_TIME)      ?: return Result.failure()
        val iceOnly  = inputData.getBoolean(KEY_ICE_ONLY, true)

        val routeKey = PriceStore.routeKey(fromId, toId, date, iceOnly)

        return try {
            val journeys = ApiClient.fetchJourneys(
                fromId, toId,
                LocalDate.parse(date),
                LocalTime.parse(time),
                iceOnly
            )

            val stored = PriceStore.loadPrices(ctx, routeKey)
            val changes = PriceStore.detectChanges(stored, journeys)

            // Persist current prices for next comparison
            val current = journeys.mapNotNull { j ->
                j.priceEur?.let { StoredPrice(j.departure, it) }
            }
            PriceStore.savePrices(ctx, routeKey, current)

            // Send notifications for changed prices
            NotificationHelper.notifyChanges(ctx, fromName, toName, changes)

            Result.success()
        } catch (e: Exception) {
            // Retry on transient network errors; notify only on persistent failure
            if (runAttemptCount >= 2) {
                NotificationHelper.notifyError(ctx, "$fromName → $toName", e.message ?: "Unbekannter Fehler")
                Result.failure()
            } else {
                Result.retry()
            }
        }
    }

    companion object {
        const val KEY_FROM_ID   = "from_id"
        const val KEY_FROM_NAME = "from_name"
        const val KEY_TO_ID     = "to_id"
        const val KEY_TO_NAME   = "to_name"
        const val KEY_DATE      = "date"
        const val KEY_TIME      = "time"
        const val KEY_ICE_ONLY  = "ice_only"

        // Unique tag per route so WorkManager can cancel the right job
        fun workTag(fromId: String, toId: String, date: String, iceOnly: Boolean) =
            "monitor_${fromId}_${toId}_${date}_${if (iceOnly) "ice" else "all"}"

        fun buildRequest(
            fromId: String, fromName: String,
            toId: String, toName: String,
            date: String, time: String,
            iceOnly: Boolean,
            intervalMinutes: Long
        ): PeriodicWorkRequest {
            val data = Data.Builder()
                .putString(KEY_FROM_ID,   fromId)
                .putString(KEY_FROM_NAME, fromName)
                .putString(KEY_TO_ID,     toId)
                .putString(KEY_TO_NAME,   toName)
                .putString(KEY_DATE,      date)
                .putString(KEY_TIME,      time)
                .putBoolean(KEY_ICE_ONLY, iceOnly)
                .build()

            return PeriodicWorkRequestBuilder<PriceMonitorWorker>(
                intervalMinutes, TimeUnit.MINUTES
            )
                .setInputData(data)
                .addTag(workTag(fromId, toId, date, iceOnly))
                .build()
        }
    }
}
