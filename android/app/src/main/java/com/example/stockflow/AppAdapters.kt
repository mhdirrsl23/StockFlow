package com.example.stockflow

import android.graphics.Typeface
import android.view.ViewGroup
import android.widget.LinearLayout
import android.widget.TextView
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.RecyclerView

open class SimpleCardAdapter<T>(
    private var items: List<T>,
    private val mapper: (T) -> RowModel,
    private val onClick: ((T) -> Unit)? = null
) : RecyclerView.Adapter<SimpleCardAdapter.VH>() {

    data class RowModel(val title: String, val sub: String, val badge: String, val colorRes: Int)
    class VH(val root: LinearLayout, val tv1: TextView, val tv2: TextView, val tv3: TextView) : RecyclerView.ViewHolder(root)

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val ctx = parent.context
        val d = ctx.resources.displayMetrics.density
        val pad = (14 * d).toInt()
        val root = LinearLayout(ctx).apply {
            orientation = LinearLayout.VERTICAL
            setBackgroundResource(R.drawable.bg_card)
            setPadding(pad, pad, pad, pad)
            layoutParams = RecyclerView.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
                bottomMargin = (10 * d).toInt()
            }
        }
        fun txt(sp: Float, color: Int, bold: Boolean = false, topDp: Int = 0) = TextView(ctx).apply {
            textSize = sp
            setTextColor(ContextCompat.getColor(ctx, color))
            if (bold) setTypeface(null, Typeface.BOLD)
            if (topDp > 0) layoutParams = LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
                topMargin = (topDp * d).toInt()
            }
        }
        val tv1 = txt(14f, R.color.sf_ink, bold = true)
        val tv2 = txt(11f, R.color.sf_muted, topDp = 3)
        val tv3 = txt(11f, R.color.sf_brand, bold = true, topDp = 6)
        root.addView(tv1); root.addView(tv2); root.addView(tv3)
        return VH(root, tv1, tv2, tv3)
    }

    override fun onBindViewHolder(holder: VH, position: Int) {
        val item = items[position]
        val row = mapper(item)
        holder.tv1.text = row.title
        holder.tv2.text = row.sub
        holder.tv3.text = row.badge
        holder.tv3.setTextColor(ContextCompat.getColor(holder.root.context, row.colorRes))
        holder.root.setOnClickListener { onClick?.invoke(item) }
    }

    override fun getItemCount(): Int = items.size

    fun updateData(newItems: List<T>) {
        items = newItems
        notifyDataSetChanged()
    }
}

class BarangAdapter(items: List<BarangItem>, onClick: (BarangItem) -> Unit) : SimpleCardAdapter<BarangItem>(items, {
    val menipis = it.total_stok_tersedia <= it.stok_minimum
    RowModel(
        it.nama,
        "${it.kode_sku} • ${it.kategori} • Min: ${it.stok_minimum} ${it.satuan}",
        "Stok Tersedia: ${it.total_stok_tersedia} ${it.satuan}" + if (menipis) " (Stok Menipis)" else "",
        if (menipis) R.color.sf_danger else R.color.sf_safe
    )
}, onClick)

class BatchAdapter(items: List<BatchStokItem>, onClick: (BatchStokItem) -> Unit) : SimpleCardAdapter<BatchStokItem>(items, {
    val color = when (it.status) {
        "Kedaluwarsa" -> R.color.sf_danger
        "Mendekati Kedaluwarsa" -> R.color.sf_warn
        "Ditangani" -> R.color.sf_muted
        else -> R.color.sf_safe
    }
    RowModel(
        "${it.barang?.nama?.plus(" • ") ?: ""}Batch ${it.nomor_batch}",
        "Kedaluwarsa: ${it.tanggal_kedaluwarsa} • Sisa: ${it.jumlah_sisa} / ${it.jumlah_awal}",
        it.status,
        color
    )
}, onClick)

class MutasiAdapter(items: List<MutasiStokItem>) : SimpleCardAdapter<MutasiStokItem>(items, {
    val tanda = if (it.jenis == "keluar" || it.jenis == "penanganan") "-" else "+"
    val color = when (it.jenis) {
        "masuk" -> R.color.sf_safe
        "keluar" -> R.color.sf_info
        "penanganan" -> R.color.sf_danger
        else -> R.color.sf_warn
    }
    RowModel("${it.jenis.uppercase()} • Batch #${it.batch_id} ($tanda${it.jumlah})", it.catatan ?: "Tanpa catatan", it.waktu, color)
})

class RiwayatAdapter(items: List<RiwayatAktivitasItem>) : SimpleCardAdapter<RiwayatAktivitasItem>(items, {
    RowModel(it.aksi.replace("_", " ").uppercase(), it.keterangan ?: "-", it.waktu, R.color.sf_muted)
})

class NotifikasiAdapter(items: List<NotifikasiItem>, onClick: (NotifikasiItem) -> Unit) : SimpleCardAdapter<NotifikasiItem>(items, {
    val color = when (it.jenis) {
        "Kedaluwarsa" -> R.color.sf_danger
        "Mendekati Kedaluwarsa" -> R.color.sf_warn
        else -> R.color.sf_info
    }
    RowModel(it.jenis, it.pesan, it.tanggal, color)
}, onClick)

class UserAdapter(items: List<UserItem>, onClick: (UserItem) -> Unit) : SimpleCardAdapter<UserItem>(items, {
    val aktif = it.aktif == 1
    RowModel(
        it.nama,
        "@${it.username} • Peran: ${it.peran}",
        if (aktif) "Aktif (Ketuk untuk nonaktifkan)" else "Nonaktif (Ketuk untuk aktifkan)",
        if (aktif) R.color.sf_safe else R.color.sf_danger
    )
}, onClick)