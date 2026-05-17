package com.dbtracker

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import androidx.core.app.NotificationCompat
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter
import java.util.concurrent.atomic.AtomicInteger

object NotificationHelper {

    private const val CHANNEL_ID = "db_price_alerts"
    private val notifIdCounter = AtomicInteger(1000)
    private val timeFmt = DateTimeFormatter.ofPattern("HH:mm")

    fun createChannel(ctx: Context) {
        val channel = NotificationChannel(
            CHANNEL_ID,
            "Preisänderungen",
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = "Benachrichtigungen bei Preisänderungen für DB-Tickets"
            enableVibration(true)
        }
        ctx.getSystemService(NotificationManager::class.java)
            .createNotificationChannel(channel)
    }

    fun notifyChanges(
        ctx: Context,
        fromName: String,
        toName: String,
        changes: List<PriceChange>
    ) {
        if (changes.isEmpty()) return

        val nm = ctx.getSystemService(NotificationManager::class.java)
        val openIntent = PendingIntent.getActivity(
            ctx, 0,
            Intent(ctx, MainActivity::class.java),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        changes.forEach { change ->
            val depTime = try {
                OffsetDateTime.parse(change.departure).format(timeFmt)
            } catch (e: Exception) { change.departure.take(5) }

            val arrow = if (change.isDropped) "▼" else "▲"
            val emoji = if (change.isDropped) "🎉" else "⚠️"
            val colorInt = if (change.isDropped) 0xFF2E7D32L.toInt() else 0xFFCC0000L.toInt()

            val title = "$emoji ${change.trainName ?: "Zug"} · $depTime Uhr · $arrow ${String.format("%.2f €", change.newPrice)}"
            val body = buildString {
                append("$fromName → $toName\n")
                append("War: ${String.format("%.2f €", change.oldPrice)}")
                append(" · Jetzt: ${String.format("%.2f €", change.newPrice)}")
                append(" (${String.format("%+.2f €", change.delta)}, ${String.format("%+.1f%%", change.deltaPct)})")
            }

            val notif = NotificationCompat.Builder(ctx, CHANNEL_ID)
                .setSmallIcon(android.R.drawable.ic_dialog_info)
                .setContentTitle(title)
                .setContentText(body)
                .setStyle(NotificationCompat.BigTextStyle().bigText(body))
                .setColor(colorInt)
                .setAutoCancel(true)
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setContentIntent(openIntent)
                .build()

            nm.notify(notifIdCounter.getAndIncrement(), notif)
        }
    }

    fun notifyError(ctx: Context, routeLabel: String, error: String) {
        val nm = ctx.getSystemService(NotificationManager::class.java)
        val notif = NotificationCompat.Builder(ctx, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_alert)
            .setContentTitle("Fehler beim Abrufen: $routeLabel")
            .setContentText(error)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .build()
        nm.notify(notifIdCounter.getAndIncrement(), notif)
    }
}
