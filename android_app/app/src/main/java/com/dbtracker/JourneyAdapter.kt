package com.dbtracker

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter

class JourneyAdapter(private val items: List<Journey>) :
    RecyclerView.Adapter<JourneyAdapter.VH>() {

    private val timeFmt = DateTimeFormatter.ofPattern("HH:mm")

    class VH(view: View) : RecyclerView.ViewHolder(view) {
        val tvTrain: TextView = view.findViewById(R.id.tvTrain)
        val tvDep: TextView = view.findViewById(R.id.tvDep)
        val tvArr: TextView = view.findViewById(R.id.tvArr)
        val tvDuration: TextView = view.findViewById(R.id.tvDuration)
        val tvPrice: TextView = view.findViewById(R.id.tvPrice)
        val tvChanges: TextView = view.findViewById(R.id.tvChanges)
        val tvOffer: TextView = view.findViewById(R.id.tvOffer)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val v = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_journey, parent, false)
        return VH(v)
    }

    override fun getItemCount() = items.size

    override fun onBindViewHolder(h: VH, pos: Int) {
        val j = items[pos]

        h.tvTrain.text = j.trainName ?: "Zug"

        try {
            h.tvDep.text = OffsetDateTime.parse(j.departure).format(timeFmt)
            h.tvArr.text = OffsetDateTime.parse(j.arrival).format(timeFmt)
        } catch (_: Exception) {
            h.tvDep.text = j.departure.take(5)
            h.tvArr.text = j.arrival.take(5)
        }

        val hh = j.durationMin / 60
        val mm = j.durationMin % 60
        h.tvDuration.text = if (hh > 0) "${hh}h ${mm}min" else "${mm}min"

        h.tvPrice.text = if (j.priceEur != null)
            String.format("%.2f €", j.priceEur)
        else
            "— €"

        h.tvChanges.text = when (j.changes) {
            0 -> "Direktverbindung"
            1 -> "1 Umstieg"
            else -> "${j.changes} Umstiege"
        }

        h.tvOffer.text = j.offerName ?: ""
        h.tvOffer.visibility = if (j.offerName != null) View.VISIBLE else View.GONE
    }
}
