package com.example.stockflow

import android.graphics.Typeface
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.FrameLayout
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.RecyclerView
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

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

class BarangAdapter(
    private var items: List<BarangItem>,
    private val onClick: (BarangItem) -> Unit
) : RecyclerView.Adapter<BarangAdapter.VH>() {

    class VH(view: View) : RecyclerView.ViewHolder(view) {
        val bgIcon: FrameLayout = view.findViewById(R.id.bg_icon_kategori)
        val ivIcon: ImageView = view.findViewById(R.id.iv_icon_kategori)
        val tvNama: TextView = view.findViewById(R.id.tv_nama_barang)
        val tvSku: TextView = view.findViewById(R.id.tv_sku_barang)
        val tvKategori: TextView = view.findViewById(R.id.tv_kategori_barang)
        val tvStokAngka: TextView = view.findViewById(R.id.tv_stok_angka)
        val tvStokSatuan: TextView = view.findViewById(R.id.tv_stok_satuan)
        val tvStatus: TextView = view.findViewById(R.id.tv_status_stok)
        val divider: View = view.findViewById(R.id.divider_item)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val view = LayoutInflater.from(parent.context).inflate(R.layout.item_barang, parent, false)
        return VH(view)
    }

    override fun onBindViewHolder(holder: VH, position: Int) {
        val item = items[position]
        val menipis = item.total_stok_tersedia <= item.stok_minimum

        holder.tvNama.text = item.nama
        holder.tvSku.text = item.kode_sku
        holder.tvKategori.text = " · ${item.kategori} · Min ${item.stok_minimum} ${item.satuan}"
        holder.tvStokAngka.text = item.total_stok_tersedia.toString()
        holder.tvStokSatuan.text = item.satuan

        when (item.kategori) {
            "Makanan" -> {
                holder.bgIcon.setBackgroundResource(R.drawable.bg_product_food)
                holder.ivIcon.setImageResource(R.drawable.ic_cookie)
                holder.ivIcon.setColorFilter(0xFF9A6515.toInt())
            }
            "Minuman" -> {
                holder.bgIcon.setBackgroundResource(R.drawable.bg_product_drink)
                holder.ivIcon.setImageResource(R.drawable.ic_cup)
                holder.ivIcon.setColorFilter(0xFF2C6B93.toInt())
            }
            "Obat" -> {
                holder.bgIcon.setBackgroundResource(R.drawable.bg_product_med)
                holder.ivIcon.setImageResource(R.drawable.ic_pill)
                holder.ivIcon.setColorFilter(0xFF9D4D59.toInt())
            }
            "Kebutuhan Rumah" -> {
                holder.bgIcon.setBackgroundResource(R.drawable.bg_product_home)
                holder.ivIcon.setImageResource(R.drawable.ic_droplet)
                holder.ivIcon.setColorFilter(0xFF397A55.toInt())
            }
            else -> {
                holder.bgIcon.setBackgroundResource(R.drawable.bg_product_other)
                holder.ivIcon.setImageResource(R.drawable.ic_package)
                holder.ivIcon.setColorFilter(0xFF66756C.toInt())
            }
        }

        if (menipis) {
            holder.tvStatus.text = "Menipis"
            holder.tvStatus.setBackgroundResource(R.drawable.bg_pill_warn)
            holder.tvStatus.setTextColor(ContextCompat.getColor(holder.itemView.context, R.color.sf_warn_ink))
            holder.tvStatus.setCompoundDrawablesWithIntrinsicBounds(R.drawable.ic12_alert_triangle, 0, 0, 0)
        } else {
            holder.tvStatus.text = "Aman"
            holder.tvStatus.setBackgroundResource(R.drawable.bg_pill_safe)
            holder.tvStatus.setTextColor(ContextCompat.getColor(holder.itemView.context, R.color.sf_safe))
            holder.tvStatus.setCompoundDrawablesWithIntrinsicBounds(R.drawable.ic12_check_circle, 0, 0, 0)
        }

        holder.divider.visibility = if (position == items.lastIndex) View.GONE else View.VISIBLE
        holder.itemView.setOnClickListener { onClick(item) }
    }

    override fun getItemCount(): Int = items.size

    fun updateData(newItems: List<BarangItem>) {
        items = newItems
        notifyDataSetChanged()
    }
}

class BatchAdapter(
    private var items: List<BatchStokItem>,
    private val onClick: (BatchStokItem) -> Unit
) : RecyclerView.Adapter<BatchAdapter.VH>() {

    class VH(view: View) : RecyclerView.ViewHolder(view) {
        val bgIcon: FrameLayout = view.findViewById(R.id.bg_icon_batch)
        val ivIcon: ImageView = view.findViewById(R.id.iv_icon_batch)
        val tvNomor: TextView = view.findViewById(R.id.tv_nomor_batch)
        val tvInfo: TextView = view.findViewById(R.id.tv_info_batch)
        val tvStatus: TextView = view.findViewById(R.id.tv_status_batch)
        val tvSisaHari: TextView = view.findViewById(R.id.tv_sisa_hari)
        val divider: View = view.findViewById(R.id.divider_batch)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val view = LayoutInflater.from(parent.context).inflate(R.layout.item_batch, parent, false)
        return VH(view)
    }

    override fun onBindViewHolder(holder: VH, position: Int) {
        val item = items[position]
        val ctx = holder.itemView.context
        val expDate = (item.tanggal_kedaluwarsa ?: "").take(10)

        holder.tvNomor.text = if (item.nama_barang != null) "${item.nomor_batch} · ${item.nama_barang}" else item.nomor_batch
        holder.tvInfo.text = "Sisa ${item.jumlah_sisa} / ${item.jumlah_awal} · exp $expDate"

        when (item.status) {
            "Kedaluwarsa" -> {
                holder.tvStatus.text = "Kedaluwarsa"
                holder.tvStatus.setBackgroundResource(R.drawable.bg_pill_warn)
                holder.tvStatus.setTextColor(ContextCompat.getColor(ctx, R.color.sf_danger))
                holder.tvStatus.setCompoundDrawablesWithIntrinsicBounds(R.drawable.ic12_alert_triangle, 0, 0, 0)
                holder.bgIcon.setBackgroundResource(R.drawable.bg_ic_danger)
                holder.ivIcon.setColorFilter(ContextCompat.getColor(ctx, R.color.sf_danger))
            }
            "Mendekati Kedaluwarsa" -> {
                holder.tvStatus.text = "Mendekati"
                holder.tvStatus.setBackgroundResource(R.drawable.bg_pill_warn)
                holder.tvStatus.setTextColor(ContextCompat.getColor(ctx, R.color.sf_warn_ink))
                holder.tvStatus.setCompoundDrawablesWithIntrinsicBounds(R.drawable.ic12_alert_triangle, 0, 0, 0)
                holder.bgIcon.setBackgroundResource(R.drawable.bg_ic_mint)
                holder.ivIcon.setColorFilter(ContextCompat.getColor(ctx, R.color.sf_warn_ink))
            }
            "Ditangani" -> {
                holder.tvStatus.text = "Ditangani"
                holder.tvStatus.setBackgroundResource(R.drawable.bg_pill_muted)
                holder.tvStatus.setTextColor(ContextCompat.getColor(ctx, R.color.sf_muted))
                holder.tvStatus.setCompoundDrawablesWithIntrinsicBounds(0, 0, 0, 0)
                holder.bgIcon.setBackgroundResource(R.drawable.bg_ic_muted)
                holder.ivIcon.setColorFilter(ContextCompat.getColor(ctx, R.color.sf_muted))
            }
            else -> {
                holder.tvStatus.text = "Aman"
                holder.tvStatus.setBackgroundResource(R.drawable.bg_pill_safe)
                holder.tvStatus.setTextColor(ContextCompat.getColor(ctx, R.color.sf_safe))
                holder.tvStatus.setCompoundDrawablesWithIntrinsicBounds(R.drawable.ic12_check_circle, 0, 0, 0)
                holder.bgIcon.setBackgroundResource(R.drawable.bg_ic_mint)
                holder.ivIcon.setColorFilter(ContextCompat.getColor(ctx, R.color.sf_brand))
            }
        }

        val sisaHariText = runCatching {
            val format = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault())
            val target = format.parse(expDate) ?: return@runCatching item.status
            val today = Calendar.getInstance().apply {
                set(Calendar.HOUR_OF_DAY, 0); set(Calendar.MINUTE, 0); set(Calendar.SECOND, 0); set(Calendar.MILLISECOND, 0)
            }.time
            val days = (target.time - today.time) / (1000 * 60 * 60 * 24)
            when {
                days < 0 -> "Lewat ${-days} hr"
                days == 0L -> "Hari ini"
                else -> "$days hr lagi"
            }
        }.getOrElse { item.status }

        holder.tvSisaHari.text = sisaHariText
        holder.divider.visibility = if (position == items.lastIndex) View.GONE else View.VISIBLE
        holder.itemView.setOnClickListener { onClick(item) }
    }

    override fun getItemCount(): Int = items.size

    fun updateData(newItems: List<BatchStokItem>) {
        items = newItems
        notifyDataSetChanged()
    }
}

class BatchCardAdapter(
    private var items: List<BatchStokItem>,
    private val onClick: (BatchStokItem) -> Unit
) : RecyclerView.Adapter<BatchCardAdapter.VH>() {

    class VH(view: View) : RecyclerView.ViewHolder(view) {
        val bgIcon: FrameLayout = view.findViewById(R.id.bg_icon_card_batch)
        val ivIcon: ImageView = view.findViewById(R.id.iv_icon_card_batch)
        val tvNamaBarang: TextView = view.findViewById(R.id.tv_card_nama_barang)
        val tvNomorBatch: TextView = view.findViewById(R.id.tv_card_nomor_batch)
        val tvPillStatus: TextView = view.findViewById(R.id.tv_card_pill_status)
        val tvSisaAwal: TextView = view.findViewById(R.id.tv_card_sisa_awal)
        val tvSatuan: TextView = view.findViewById(R.id.tv_card_satuan)
        val tvKedaluwarsa: TextView = view.findViewById(R.id.tv_card_kedaluwarsa)
        val tvStatusDesc: TextView = view.findViewById(R.id.tv_card_status_desc)
        val pbBatch: ProgressBar = view.findViewById(R.id.pb_card_batch)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val view = LayoutInflater.from(parent.context).inflate(R.layout.item_batch_card, parent, false)
        return VH(view)
    }

    override fun onBindViewHolder(holder: VH, position: Int) {
        val item = items[position]
        val ctx = holder.itemView.context
        val expDate = (item.tanggal_kedaluwarsa ?: "").take(10)

        holder.tvNamaBarang.text = item.nama_barang ?: "Barang #${item.barang_id}"
        holder.tvNomorBatch.text = item.nomor_batch
        holder.tvSisaAwal.text = "${item.jumlah_sisa} / ${item.jumlah_awal}"
        holder.tvSatuan.text = "pcs"
        holder.tvKedaluwarsa.text = expDate

        val progress = if (item.jumlah_awal > 0) ((item.jumlah_sisa * 100) / item.jumlah_awal).coerceIn(0, 100) else 0
        holder.pbBatch.progress = progress

        // Kontrak baru: item batch tidak membawa data barang (kategori) — gunakan ikon generik.
        holder.bgIcon.setBackgroundResource(R.drawable.bg_product_other)
        holder.ivIcon.setImageResource(R.drawable.ic_package)
        holder.ivIcon.setColorFilter(0xFF66756C.toInt())

        val days = runCatching {
            val format = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault())
            val target = format.parse(expDate) ?: return@runCatching 0L
            val today = Calendar.getInstance().apply {
                set(Calendar.HOUR_OF_DAY, 0); set(Calendar.MINUTE, 0); set(Calendar.SECOND, 0); set(Calendar.MILLISECOND, 0)
            }.time
            (target.time - today.time) / (1000 * 60 * 60 * 24)
        }.getOrNull()

        when (item.status) {
            "Kedaluwarsa" -> {
                holder.tvPillStatus.text = "Kedaluwarsa"
                holder.tvPillStatus.setBackgroundResource(R.drawable.bg_pill_danger)
                holder.tvPillStatus.setTextColor(ContextCompat.getColor(ctx, R.color.sf_danger))
                holder.tvPillStatus.setCompoundDrawablesWithIntrinsicBounds(R.drawable.ic12_alert_triangle, 0, 0, 0)
                holder.tvStatusDesc.text = if (days != null && days < 0) "Kedaluwarsa ${-days} hr lalu" else "Sudah kedaluwarsa"
                holder.pbBatch.progressTintList = ContextCompat.getColorStateList(ctx, R.color.sf_danger)
            }
            "Mendekati Kedaluwarsa" -> {
                holder.tvPillStatus.text = "Mendekati"
                holder.tvPillStatus.setBackgroundResource(R.drawable.bg_pill_warn)
                holder.tvPillStatus.setTextColor(ContextCompat.getColor(ctx, R.color.sf_warn_ink))
                holder.tvPillStatus.setCompoundDrawablesWithIntrinsicBounds(R.drawable.ic12_clock, 0, 0, 0)
                holder.tvStatusDesc.text = if (days != null) "Kedaluwarsa dalam $days hr lagi" else "Mendekati kedaluwarsa"
                holder.pbBatch.progressTintList = ContextCompat.getColorStateList(ctx, R.color.sf_warn)
            }
            "Ditangani" -> {
                holder.tvPillStatus.text = "Ditangani"
                holder.tvPillStatus.setBackgroundResource(R.drawable.bg_pill_muted)
                holder.tvPillStatus.setTextColor(ContextCompat.getColor(ctx, R.color.sf_muted))
                holder.tvPillStatus.setCompoundDrawablesWithIntrinsicBounds(R.drawable.ic12_archive, 0, 0, 0)
                holder.tvStatusDesc.text = "Ditangani · Dimusnahkan"
                holder.pbBatch.progressTintList = ContextCompat.getColorStateList(ctx, R.color.sf_muted)
            }
            else -> {
                holder.tvPillStatus.text = "Aman"
                holder.tvPillStatus.setBackgroundResource(R.drawable.bg_pill_safe)
                holder.tvPillStatus.setTextColor(ContextCompat.getColor(ctx, R.color.sf_safe))
                holder.tvPillStatus.setCompoundDrawablesWithIntrinsicBounds(R.drawable.ic12_check_circle, 0, 0, 0)
                holder.tvStatusDesc.text = if (days != null) "Kedaluwarsa dalam $days hr lagi" else "Stok aman"
                holder.pbBatch.progressTintList = ContextCompat.getColorStateList(ctx, R.color.sf_brand)
            }
        }

        holder.itemView.setOnClickListener { onClick(item) }
    }

    override fun getItemCount(): Int = items.size

    fun updateData(newItems: List<BatchStokItem>) {
        items = newItems
        notifyDataSetChanged()
    }
}

class MutasiAdapter(items: List<MutasiStokItem>) : SimpleCardAdapter<MutasiStokItem>(items, {
    val tanda = if (it.jenis == "keluar" || it.jenis == "penanganan") "-" else "+"
    val color = when (it.jenis) {
        "masuk" -> R.color.sf_safe
        "keluar" -> R.color.sf_info
        "penanganan" -> R.color.sf_danger
        else -> R.color.sf_warn
    }
    RowModel("${it.jenis.uppercase()} · Batch #${it.batch_id} ($tanda${it.jumlah})", it.catatan ?: "Tanpa catatan", it.waktu, color)
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
    RowModel(it.jenis, it.pesan ?: "-", it.tanggal ?: "-", color)
}, onClick)

class UserAdapter(items: List<UserItem>, onClick: (UserItem) -> Unit) : SimpleCardAdapter<UserItem>(items, {
    val aktif = it.aktif
    RowModel(
        it.nama,
        "@${it.username} · Peran: ${it.peran}",
        if (aktif) "Aktif (Ketuk untuk nonaktifkan)" else "Nonaktif (Ketuk untuk aktifkan)",
        if (aktif) R.color.sf_safe else R.color.sf_danger
    )
}, onClick)