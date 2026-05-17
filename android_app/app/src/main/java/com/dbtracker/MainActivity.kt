package com.dbtracker

import android.Manifest
import android.app.DatePickerDialog
import android.app.TimePickerDialog
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.view.View
import android.widget.ArrayAdapter
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.WorkManager
import com.dbtracker.databinding.ActivityMainBinding
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.time.LocalDate
import java.time.LocalTime
import java.time.format.DateTimeFormatter
import java.util.Locale

class MainActivity : AppCompatActivity() {

    private lateinit var b: ActivityMainBinding
    private val stations = ApiClient.popularStations
    private var selectedDate = LocalDate.now().plusDays(1)
    private var selectedTime = LocalTime.of(6, 0)

    // Interval options (minutes) — WorkManager minimum is 15 min
    private val intervalOptions = listOf(15L to "15 Minuten", 30L to "30 Minuten",
        60L to "1 Stunde", 120L to "2 Stunden", 240L to "4 Stunden")

    private val notifPermLauncher =
        registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
            if (granted) scheduleMonitor() else
                Toast.makeText(this, "Benachrichtigungen wurden nicht erlaubt", Toast.LENGTH_SHORT).show()
        }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        b = ActivityMainBinding.inflate(layoutInflater)
        setContentView(b.root)

        setSupportActionBar(b.toolbar)
        NotificationHelper.createChannel(this)

        setupSpinners()
        setupDatePicker()
        setupTimePicker()
        setupMonitorIntervalSpinner()

        b.rvJourneys.layoutManager = LinearLayoutManager(this)
        b.rvJourneys.setHasFixedSize(false)

        b.spinnerFrom.setSelection(stations.indexOfFirst { it.id == "8000096" }.coerceAtLeast(0))
        b.spinnerTo.setSelection(stations.indexOfFirst { it.id == "8011160" }.coerceAtLeast(1))

        updateDateDisplay()
        updateTimeDisplay()

        b.btnSearch.setOnClickListener { startSearch() }
        b.btnSwap.setOnClickListener {
            val f = b.spinnerFrom.selectedItemPosition
            val t = b.spinnerTo.selectedItemPosition
            b.spinnerFrom.setSelection(t)
            b.spinnerTo.setSelection(f)
        }
        b.btnMonitor.setOnClickListener { onMonitorClicked() }

        refreshMonitorStatus()
    }

    override fun onResume() {
        super.onResume()
        refreshMonitorStatus()
    }

    // ─── Spinners ───────────────────────────────────────────────────────────

    private fun setupSpinners() {
        val stationAdapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, stations)
        b.spinnerFrom.adapter = stationAdapter
        b.spinnerTo.adapter = stationAdapter
    }

    private fun setupMonitorIntervalSpinner() {
        val labels = intervalOptions.map { it.second }
        b.spinnerInterval.adapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, labels)
        b.spinnerInterval.setSelection(1) // default: 30 min
    }

    // ─── Date / Time ────────────────────────────────────────────────────────

    private fun setupDatePicker() {
        b.tvDate.setOnClickListener {
            DatePickerDialog(
                this,
                { _, y, m, d ->
                    selectedDate = LocalDate.of(y, m + 1, d)
                    updateDateDisplay()
                    refreshMonitorStatus()
                },
                selectedDate.year, selectedDate.monthValue - 1, selectedDate.dayOfMonth
            ).also { it.datePicker.minDate = System.currentTimeMillis() }.show()
        }
    }

    private fun setupTimePicker() {
        b.tvTime.setOnClickListener {
            TimePickerDialog(
                this,
                { _, h, m ->
                    selectedTime = LocalTime.of(h, m)
                    updateTimeDisplay()
                    refreshMonitorStatus()
                },
                selectedTime.hour, selectedTime.minute, true
            ).show()
        }
    }

    private fun updateDateDisplay() {
        b.tvDate.text = selectedDate.format(DateTimeFormatter.ofPattern("EEE, dd. MMMM yyyy", Locale.GERMAN))
    }

    private fun updateTimeDisplay() {
        b.tvTime.text = "ab ${selectedTime.format(DateTimeFormatter.ofPattern("HH:mm"))} Uhr"
    }

    // ─── Search ─────────────────────────────────────────────────────────────

    private fun startSearch() {
        val from = stations[b.spinnerFrom.selectedItemPosition]
        val to = stations[b.spinnerTo.selectedItemPosition]
        if (from.id == to.id) {
            Toast.makeText(this, "Bitte unterschiedliche Bahnhöfe wählen", Toast.LENGTH_SHORT).show()
            return
        }

        b.progressOverlay.visibility = View.VISIBLE
        b.tvStatus.visibility = View.VISIBLE
        b.tvStatus.text = "Suche Verbindungen…"
        b.rvJourneys.adapter = null

        lifecycleScope.launch {
            try {
                val journeys = withContext(Dispatchers.IO) {
                    ApiClient.fetchJourneys(from.id, to.id, selectedDate, selectedTime, b.switchIceOnly.isChecked)
                }
                b.progressOverlay.visibility = View.GONE
                if (journeys.isEmpty()) {
                    b.tvStatus.text = "Keine Verbindungen gefunden"
                } else {
                    b.tvStatus.text = "${journeys.size} Verbindungen · ${from.name} → ${to.name}"
                    b.rvJourneys.adapter = JourneyAdapter(journeys)

                    // Persist prices as initial baseline for monitoring
                    val key = PriceStore.routeKey(from.id, to.id, selectedDate.toString(), b.switchIceOnly.isChecked)
                    val stored = PriceStore.loadPrices(this@MainActivity, key)
                    if (stored.isEmpty()) {
                        val prices = journeys.mapNotNull { j -> j.priceEur?.let { StoredPrice(j.departure, it) } }
                        PriceStore.savePrices(this@MainActivity, key, prices)
                    }
                }
            } catch (e: Exception) {
                b.progressOverlay.visibility = View.GONE
                b.tvStatus.text = "Fehler: ${e.message}"
            }
        }
    }

    // ─── Monitor ────────────────────────────────────────────────────────────

    private fun currentRouteKey(): String {
        val from = stations[b.spinnerFrom.selectedItemPosition]
        val to = stations[b.spinnerTo.selectedItemPosition]
        return PriceStore.routeKey(from.id, to.id, selectedDate.toString(), b.switchIceOnly.isChecked)
    }

    private fun currentWorkTag(): String {
        val from = stations[b.spinnerFrom.selectedItemPosition]
        val to = stations[b.spinnerTo.selectedItemPosition]
        return PriceMonitorWorker.workTag(from.id, to.id, selectedDate.toString(), b.switchIceOnly.isChecked)
    }

    private fun onMonitorClicked() {
        val key = currentRouteKey()
        if (PriceStore.isMonitorActive(this, key)) {
            stopMonitor()
        } else {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
                ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS)
                != PackageManager.PERMISSION_GRANTED
            ) {
                notifPermLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
            } else {
                scheduleMonitor()
            }
        }
    }

    private fun scheduleMonitor() {
        val from = stations[b.spinnerFrom.selectedItemPosition]
        val to = stations[b.spinnerTo.selectedItemPosition]
        if (from.id == to.id) {
            Toast.makeText(this, "Bitte unterschiedliche Bahnhöfe wählen", Toast.LENGTH_SHORT).show()
            return
        }

        val intervalMin = intervalOptions[b.spinnerInterval.selectedItemPosition].first
        val workTag = currentWorkTag()

        val request = PriceMonitorWorker.buildRequest(
            fromId = from.id, fromName = from.name,
            toId = to.id, toName = to.name,
            date = selectedDate.toString(),
            time = selectedTime.toString(),
            iceOnly = b.switchIceOnly.isChecked,
            intervalMinutes = intervalMin
        )

        WorkManager.getInstance(this)
            .enqueueUniquePeriodicWork(workTag, ExistingPeriodicWorkPolicy.CANCEL_AND_REENQUEUE, request)

        PriceStore.setMonitorActive(this, currentRouteKey(), true)
        refreshMonitorStatus()

        Toast.makeText(
            this,
            "Überwachung gestartet: alle ${intervalOptions[b.spinnerInterval.selectedItemPosition].second}",
            Toast.LENGTH_SHORT
        ).show()
    }

    private fun stopMonitor() {
        WorkManager.getInstance(this).cancelAllWorkByTag(currentWorkTag())
        PriceStore.setMonitorActive(this, currentRouteKey(), false)
        refreshMonitorStatus()
        Toast.makeText(this, "Überwachung gestoppt", Toast.LENGTH_SHORT).show()
    }

    private fun refreshMonitorStatus() {
        val active = PriceStore.isMonitorActive(this, currentRouteKey())
        b.btnMonitor.text = if (active) "■ Stop" else "▶ Starten"
        b.tvMonitorStatus.text = if (active) "● Aktiv" else "○ Inaktiv"
        b.tvMonitorStatus.setTextColor(
            if (active) 0xFF2E7D32L.toInt() else 0xFF9E9E9EL.toInt()
        )
    }
}
