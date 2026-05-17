package com.dbtracker

import android.app.DatePickerDialog
import android.app.TimePickerDialog
import android.os.Bundle
import android.view.View
import android.widget.ArrayAdapter
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.dbtracker.databinding.ActivityMainBinding
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.time.LocalDate
import java.time.LocalTime
import java.time.format.DateTimeFormatter
import java.util.Calendar
import java.util.Locale

class MainActivity : AppCompatActivity() {

    private lateinit var b: ActivityMainBinding
    private val stations = ApiClient.popularStations
    private var selectedDate = LocalDate.now().plusDays(1)
    private var selectedTime = LocalTime.of(6, 0)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        b = ActivityMainBinding.inflate(layoutInflater)
        setContentView(b.root)

        setSupportActionBar(b.toolbar)

        setupSpinners()
        setupDatePicker()
        setupTimePicker()

        b.rvJourneys.layoutManager = LinearLayoutManager(this)
        b.rvJourneys.setHasFixedSize(false)

        // Defaults: Stuttgart → Berlin
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
    }

    private fun setupSpinners() {
        val adapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, stations)
        b.spinnerFrom.adapter = adapter
        b.spinnerTo.adapter = adapter
    }

    private fun setupDatePicker() {
        b.tvDate.setOnClickListener {
            DatePickerDialog(
                this,
                { _, y, m, d ->
                    selectedDate = LocalDate.of(y, m + 1, d)
                    updateDateDisplay()
                },
                selectedDate.year, selectedDate.monthValue - 1, selectedDate.dayOfMonth
            ).also { dlg ->
                dlg.datePicker.minDate = System.currentTimeMillis()
            }.show()
        }
    }

    private fun setupTimePicker() {
        b.tvTime.setOnClickListener {
            TimePickerDialog(
                this,
                { _, h, m ->
                    selectedTime = LocalTime.of(h, m)
                    updateTimeDisplay()
                },
                selectedTime.hour, selectedTime.minute, true
            ).show()
        }
    }

    private fun updateDateDisplay() {
        b.tvDate.text = selectedDate.format(
            DateTimeFormatter.ofPattern("EEE, dd. MMMM yyyy", Locale.GERMAN)
        )
    }

    private fun updateTimeDisplay() {
        b.tvTime.text = "ab ${selectedTime.format(DateTimeFormatter.ofPattern("HH:mm"))} Uhr"
    }

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
                    ApiClient.fetchJourneys(
                        from.id, to.id,
                        selectedDate, selectedTime,
                        b.switchIceOnly.isChecked
                    )
                }
                b.progressOverlay.visibility = View.GONE
                if (journeys.isEmpty()) {
                    b.tvStatus.text = "Keine Verbindungen gefunden"
                } else {
                    b.tvStatus.text = "${journeys.size} Verbindungen · ${from.name} → ${to.name}"
                    b.rvJourneys.adapter = JourneyAdapter(journeys)
                }
            } catch (e: Exception) {
                b.progressOverlay.visibility = View.GONE
                b.tvStatus.text = "Fehler: ${e.message}"
            }
        }
    }
}
