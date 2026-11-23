package com.example.stockflow

import android.app.AlertDialog
import android.app.DatePickerDialog
import android.content.Intent
import android.content.res.ColorStateList
import android.graphics.Color
import android.net.Uri
import android.os.Bundle
import android.text.Editable
import android.text.InputType
import android.text.TextWatcher
import android.view.View
import android.view.ViewGroup
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import androidx.core.os.bundleOf
import androidx.core.content.FileProvider
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.google.gson.Gson
import com.google.gson.JsonObject
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import java.io.File
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import java.util.TimeZone
import java.util.concurrent.TimeUnit

private inline fun <reified T : View> View.opt(idName: String): T? {
    val id = resources.getIdentifier(idName, "id", context.packageName)
    return if (id != 0) findViewById(id) else null
}

private fun Fragment.go(destName: String, args: Bundle? = null) {
    val id = resources.getIdentifier(destName, "id", requireContext().packageName)
    if (id != 0) runCatching { findNavController().navigate(id, args) }
}

/**
 * Pasang aksi klik pada tombol kontainer. Beberapa tombol ikon dibungkus
 * FrameLayout ber-id dengan anak ImageView berclickable (style SF.IconBtn) —
 * tanpa listener di anak, tap ditelan anak dan tombol terasa mati.
 * Pasang listener yang sama di anak clickable agar keduanya merespons.
 */
private fun View.pasangKlik(blok: (View) -> Unit) {
    setOnClickListener(blok)
    isClickable = true
    val grp = this as? ViewGroup ?: return
    fun pasangRecursively(v: View) {
        v.setOnClickListener(blok)
        v.isClickable = true
        if (v is ViewGroup) {
            for (i in 0 until v.childCount) {
                pasangRecursively(v.getChildAt(i))
            }
        }
    }
    pasangRecursively(grp)
}

private fun Fragment.runApi(block: suspend (ApiService) -> Unit) {
    viewLifecycleOwner.lifecycleScope.launch {
        runCatching { block(RetrofitClient.getInstance(requireContext())) }
    }
}

/**
 * Urai pesan gagal dari respons API: untuk status non-2xx (mis. 422 validasi)
 * isi JSON ada di errorBody — ambil message + rincian errors per kolom (PRD 9.5).
 */
private fun Fragment.uraikanGagal(res: retrofit2.Response<*>, bawaan: String): String {
    val teks = res.errorBody()?.string()
    if (teks != null) {
        return runCatching {
            val obj = Gson().fromJson(teks, JsonObject::class.java)
            val pesan = obj.get("message")?.asString ?: bawaan
            val errors = obj.getAsJsonObject("errors")
            val rincian = errors?.entrySet()?.take(3)?.joinToString("\n") { (kunci, nilai) ->
                "• ${(nilai.asJsonArray.firstOrNull()?.asString ?: kunci)}"
            }
            if (rincian.isNullOrEmpty()) pesan else "$pesan\n$rincian"
        }.getOrDefault(bawaan)
    }
    return (res.body() as? ApiResponse<*>)?.message ?: bawaan
}

private fun <VH : RecyclerView.ViewHolder> View.bindList(rvId: String, adapter: RecyclerView.Adapter<VH>) {
    opt<RecyclerView>(rvId)?.apply {
        layoutManager = LinearLayoutManager(context)
        this.adapter = adapter
    }
}

open class BaseBerandaFragment(private val layoutId: Int, private val fallbackNama: String) : Fragment(layoutId) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        val nama = SessionManager(requireContext()).getNama().ifEmpty { fallbackNama }
        view.opt<TextView>("tv_sapaan")?.text = "Halo, $nama"

        mapOf(
            "btn_notifikasi" to "notifikasiFragment",
            "btn_notifikasi_staff" to "notifikasiFragment",
            "btn_perlu_ditangani" to "perluDitanganiFragment",
            "btn_tambah_barang" to "formBarangFragment",
            "btn_tambah_batch" to "formBatchFragment",
            "btn_stok_keluar" to "formStokKeluarFragment",
            "btn_lihat_semua_barang" to "nav_barang",
            "btn_lihat_laporan" to "nav_laporan",
            "btn_lihat_riwayat" to "nav_riwayat",
            // Kartu alert & stat beranda
            "card_alert_kedaluwarsa" to "perluDitanganiFragment",
            "card_alert_menipis" to "nav_barang",
            "card_stat_kedaluwarsa" to "perluDitanganiFragment",
            "card_stat_mendekati" to "perluDitanganiFragment",
            "card_hero_perlu_ditangani" to "perluDitanganiFragment",
            "card_stat_menipis" to "nav_barang",
            // Aksi beranda staf
            "btn_stok_masuk" to "formBatchFragment",
            "btn_tangani" to "perluDitanganiFragment",
            "btn_barang_menipis" to "nav_barang",
        ).forEach { (btnId, dest) ->
            view.opt<View>(btnId)?.pasangKlik { go(dest) }
        }

        runApi { api ->
            val d = api.getDashboard().body()?.data ?: return@runApi
            view.opt<TextView>("tv_total_barang")?.text = d.ringkasan.total_barang_aktif.toString()
            view.opt<TextView>("tv_total_stok")?.text = d.ringkasan.total_batch_aktif.toString()
            view.opt<TextView>("tv_stok_menipis")?.text = d.ringkasan.stok_menipis.toString()
            view.opt<TextView>("tv_mendekati_kedaluwarsa")?.text = d.ringkasan.batch_mendekati_kedaluwarsa.toString()
            view.opt<TextView>("tv_kedaluwarsa")?.text = d.ringkasan.batch_kedaluwarsa.toString()
            view.opt<TextView>("tv_perlu_ditangani")?.text = d.ringkasan.batch_kedaluwarsa.toString()

            // Angka & kalimat khusus beranda staf (id tidak ada di beranda pemilik → no-op)
            view.opt<TextView>("tv_notif_baru")?.text = d.ringkasan.notifikasi_belum_dibaca.toString()
            view.opt<TextView>("tv_kedaluwarsa_2")?.text = d.ringkasan.batch_kedaluwarsa.toString()
            view.opt<TextView>("tv_mendekati_kedaluwarsa_2")?.text = d.ringkasan.batch_mendekati_kedaluwarsa.toString()
            view.opt<TextView>("tv_stok_menipis_2")?.text = d.ringkasan.stok_menipis.toString()
            bindDashboardLanjutan(view, d)
        }
    }

    /**
     * Mengikat bagian beranda yang sebelumnya masih berisi teks demo statis:
     * tanggal, alert, prioritas FEFO, stok menipis, dan aktivitas terbaru.
     */
    private fun bindDashboardLanjutan(view: View, d: DashboardData) {
        val tanggal = SimpleDateFormat("EEE, d MMM", Locale("id", "ID")).format(Date())
        view.opt<TextView>("tv_tanggal_1")?.text = tanggal
        view.opt<TextView>("tv_tanggal_2")?.text = tanggal

        val belumDibaca = d.ringkasan.notifikasi_belum_dibaca
        view.opt<TextView>("tv_notif_badge")?.apply {
            text = belumDibaca.toString()
            visibility = if (belumDibaca > 0) View.VISIBLE else View.GONE
        }

        val perlu = d.perlu_ditangani
        val menipis = d.stok_menipis
        val jumlahKedaluwarsa = maxOf(d.ringkasan.batch_kedaluwarsa, perlu.size)
        val jumlahMenipis = maxOf(d.ringkasan.stok_menipis, menipis.size)

        view.opt<TextView>("tv_stok_menipis_besar")?.text = jumlahMenipis.toString()
        view.opt<TextView>("tv_batch_aktif")?.text = d.ringkasan.total_batch_aktif.toString()

        view.opt<TextView>("tv_alert_kedaluwarsa")?.text =
            "$jumlahKedaluwarsa batch kedaluwarsa perlu ditangani"
        view.opt<TextView>("tv_alert_kedaluwarsa_detail")?.text =
            perlu.firstOrNull()?.let {
                "${namaBarang(it)} · ${it.nomor_batch ?: "-"} · sisa ${it.jumlah_sisa ?: 0} ${satuanBarang(it)}".trim()
            } ?: "Buka Perlu Ditangani untuk melihat detail"
        view.opt<View>("card_alert_kedaluwarsa")?.visibility =
            if (jumlahKedaluwarsa > 0) View.VISIBLE else View.GONE
        view.opt<View>("card_alert_kedaluwarsa")?.pasangKlik { go("perluDitanganiFragment") }
        view.opt<View>("btn_perlu_ditangani")?.pasangKlik { go("perluDitanganiFragment") }
        view.opt<View>("btn_tangani")?.pasangKlik { go("perluDitanganiFragment") }
        view.opt<View>("card_stat_kedaluwarsa")?.pasangKlik { go("perluDitanganiFragment") }
        view.opt<View>("card_stat_mendekati")?.pasangKlik { go("perluDitanganiFragment") }
        view.opt<View>("card_hero_perlu_ditangani")?.pasangKlik { go("perluDitanganiFragment") }
        if (layoutId == R.layout.fragment_beranda_staff) {
            // Di beranda staf, kartu alert memakai id tombol yang sudah ada.
            view.opt<View>("btn_perlu_ditangani")?.visibility =
                if (jumlahKedaluwarsa > 0) View.VISIBLE else View.GONE
        }

        view.opt<TextView>("tv_alert_menipis")?.text =
            "$jumlahMenipis barang di bawah stok minimum"
        view.opt<TextView>("tv_alert_menipis_detail")?.text =
            menipis.firstOrNull()?.let {
                if (menipis.size > 1) "${namaBarang(it)} dan ${menipis.size - 1} lainnya butuh restok."
                else "${namaBarang(it)} butuh restok."
            } ?: "Semua stok berada di atas minimum"
        view.opt<View>("card_alert_menipis")?.visibility =
            if (jumlahMenipis > 0) View.VISIBLE else View.GONE
        if (layoutId == R.layout.fragment_beranda_staff) {
            // Di beranda staf, kartu alert memakai id tombol yang sudah ada.
            view.opt<View>("btn_barang_menipis")?.visibility =
                if (jumlahMenipis > 0) View.VISIBLE else View.GONE
        }

        val fefo = d.rekomendasi_fefo.take(4)
        bindDaftar(
            view,
            listOf("fefo_row_1", "fefo_row_2", "fefo_row_3", "fefo_row_4"),
            listOf("fefo_div_1", "fefo_div_2", "fefo_div_3"),
            fefo.map { b ->
                listOf(
                    namaBarang(b),
                    b.nomor_batch ?: "-",
                    "sisa ${b.jumlah_sisa ?: 0} ${satuanBarang(b)}".trim(),
                    b.status ?: "-",
                    hariRelatif(b.tanggal_kedaluwarsa)
                )
            },
            gayaStatus = true
        )
        tampilkanSeksi(view, "section_fefo_header", "section_fefo_items", fefo.isNotEmpty())

        val stok = menipis.take(2)
        bindDaftar(
            view,
            listOf("stok_row_1", "stok_row_2"),
            listOf("stok_div_1"),
            stok.map { b ->
                listOf(
                    namaBarang(b),
                    "${b.total_stok_tersedia ?: 0} / min. ${b.stok_minimum ?: 0} ${satuanBarang(b)}".trim(),
                    "Menipis"
                )
            }
        )
        tampilkanSeksi(view, "section_stok_header", "section_stok_items", stok.isNotEmpty())

        val aktivitas = d.transaksi_terbaru.take(3)
        bindDaftar(
            view,
            listOf("aktivitas_row_1", "aktivitas_row_2", "aktivitas_row_3"),
            listOf("aktivitas_div_1", "aktivitas_div_2"),
            aktivitas.map { t ->
                listOf(
                    "${labelJenis(t.jenis)} ${t.jumlah}",
                    "batch #${t.batch_id ?: "-"} · ${t.pengguna?.nama ?: "Sistem"}",
                    jamTransaksi(t.waktu)
                )
            }
        )
        tampilkanSeksi(view, "section_aktivitas_header", "section_aktivitas_items", aktivitas.isNotEmpty())
    }

    private fun bindDaftar(
        view: View,
        rowIds: List<String>,
        divIds: List<String>,
        data: List<List<String>>,
        gayaStatus: Boolean = false
    ) {
        rowIds.forEachIndexed { i, namaId ->
            val row = view.opt<View>(namaId) ?: return@forEachIndexed
            val isi = data.getOrNull(i)
            row.visibility = if (isi == null) View.GONE else View.VISIBLE
            if (isi != null && row is ViewGroup) {
                val textViews = mutableListOf<TextView>()
                fun kumpulkan(v: View) {
                    if (v is TextView) textViews.add(v)
                    else if (v is ViewGroup) for (j in 0 until v.childCount) kumpulkan(v.getChildAt(j))
                }
                kumpulkan(row)
                textViews.forEachIndexed { j, tv -> tv.text = isi.getOrElse(j) { "" } }
                if (gayaStatus) {
                    textViews.firstOrNull { tv ->
                        tv.text.toString() in setOf("Aman", "Mendekati Kedaluwarsa", "Kedaluwarsa")
                    }?.let { hiasPillStatus(it, it.text.toString()) }
                }
            }
        }
        divIds.forEachIndexed { i, namaId ->
            view.opt<View>(namaId)?.visibility = if (data.size > i + 1) View.VISIBLE else View.GONE
        }
    }

    /** Menyesuaikan warna pill status FEFO agar "Aman" tidak tampil merah. */
    private fun hiasPillStatus(pill: TextView, status: String) {
        val (latar, warna, ikon) = when (status) {
            "Aman" -> Triple(R.drawable.bg_pill_safe, R.color.sf_safe, 0)
            "Mendekati Kedaluwarsa" -> Triple(R.drawable.bg_pill_warn, R.color.sf_warn, R.drawable.ic12_alert_triangle)
            "Kedaluwarsa" -> Triple(R.drawable.bg_pill_danger, R.color.sf_danger, R.drawable.ic12_alert_triangle)
            else -> return
        }
        val warnaTeks = ContextCompat.getColor(pill.context, warna)
        pill.setBackgroundResource(latar)
        pill.setTextColor(warnaTeks)
        pill.compoundDrawableTintList = ColorStateList.valueOf(warnaTeks)
        pill.setCompoundDrawablesRelativeWithIntrinsicBounds(ikon, 0, 0, 0)
    }

    private fun namaBarang(item: ItemRingkas): String =
        item.barang?.nama ?: item.nama_barang ?: item.nama ?: "-"

    private fun satuanBarang(item: ItemRingkas): String =
        item.barang?.satuan ?: item.satuan.orEmpty()

    private fun tampilkanSeksi(view: View, headerId: String, itemsId: String, tampil: Boolean) {
        val visibilitas = if (tampil) View.VISIBLE else View.GONE
        view.opt<View>(headerId)?.visibility = visibilitas
        view.opt<View>(itemsId)?.visibility = visibilitas
    }

    private fun labelJenis(jenis: String): String = when (jenis) {
        "masuk" -> "Stok masuk"
        "keluar" -> "Stok keluar"
        "penyesuaian" -> "Penyesuaian"
        "penanganan" -> "Penanganan"
        else -> jenis.replaceFirstChar { it.uppercase() }
    }

    private fun jamTransaksi(waktu: String?): String {
        if (waktu.isNullOrBlank()) return "-"
        val bersih = waktu.replace(' ', 'T').substringBefore('.').removeSuffix("Z")
        val masuk = runCatching {
            SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", Locale.US).apply {
                timeZone = TimeZone.getTimeZone("UTC")
            }.parse(bersih)
        }.getOrNull() ?: return "-"
        return SimpleDateFormat("HH.mm", Locale("id", "ID"))
            .apply { timeZone = TimeZone.getDefault() }
            .format(Date(masuk.time))
    }

    private fun hariRelatif(tanggal: String?): String {
        if (tanggal.isNullOrBlank()) return "-"
        val pola = SimpleDateFormat("yyyy-MM-dd", Locale.US)
        val tgl = runCatching { pola.parse(tanggal) }.getOrNull() ?: return tanggal
        val hariIni = Calendar.getInstance().apply {
            set(Calendar.HOUR_OF_DAY, 0)
            set(Calendar.MINUTE, 0)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }
        val target = Calendar.getInstance().apply {
            time = tgl
            set(Calendar.HOUR_OF_DAY, 0)
            set(Calendar.MINUTE, 0)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }
        val selisih = TimeUnit.MILLISECONDS.toDays(target.timeInMillis - hariIni.timeInMillis)
        return when {
            selisih < 0 -> "${-selisih} hr lalu"
            selisih == 0L -> "hari ini"
            else -> "$selisih hr lagi"
        }
    }
}

class BerandaOwnerFragment : BaseBerandaFragment(R.layout.fragment_beranda_owner, "Pemilik")
class BerandaStaffFragment : BaseBerandaFragment(R.layout.fragment_beranda_staff, "Staf Gudang")

open class BaseBarangFragment(layoutId: Int) : Fragment(layoutId) {

    private lateinit var adapter: BarangAdapter
    private var search: String? = null
    private var kategori: String? = null

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        adapter = BarangAdapter(emptyList()) { go("detailBarangFragment", bundleOf("barangId" to it.id)) }

        // Lonceng notifikasi: id berbeda per varian layout (pemilik / staf) — opt() no-op bila tidak ada.
        view.opt<View>("btn_notifikasi")?.pasangKlik { go("notifikasiFragment") }
        view.opt<View>("btn_notifikasi_staff")?.pasangKlik { go("notifikasiFragment") }
        view.bindList("rv_barang", adapter)

        view.opt<View>("btn_tambah_barang")?.setOnClickListener { go("formBarangFragment") }
        view.opt<View>("btn_tambah_batch")?.setOnClickListener { go("formBatchFragment") }
        view.opt<View>("btn_stok_keluar")?.setOnClickListener { go("formStokKeluarFragment") }

        view.opt<EditText>("et_search")?.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                search = s?.toString()?.trim()?.ifEmpty { null }
                loadBarang()
            }
        })

        val chipMap = mapOf(
            "chip_semua" to null,
            "chip_makanan" to "Makanan",
            "chip_minuman" to "Minuman",
            "chip_obat" to "Obat",
            "chip_rumah" to "Kebutuhan Rumah",
            "chip_lainnya" to "Lainnya"
        )
        fun updateCategoryChips(selectedId: String) {
            val allChipIds = chipMap.keys + "chip_stok_menipis"
            allChipIds.forEach { id ->
                val v = view.opt<View>(id) ?: return@forEach
                val isSelected = (id == selectedId)
                if (v is TextView) {
                    if (isSelected) {
                        v.setBackgroundResource(R.drawable.bg_chip_active)
                        v.setTextColor(ContextCompat.getColor(requireContext(), R.color.sf_on_brand))
                    } else {
                        v.setBackgroundResource(R.drawable.bg_chip)
                        v.setTextColor(ContextCompat.getColor(requireContext(), R.color.sf_chip_text))
                    }
                } else if (v is ViewGroup) {
                    v.setBackgroundResource(if (isSelected) R.drawable.bg_chip_active else R.drawable.bg_chip)
                    val tv = (v.getChildAt(1) as? TextView) ?: (v.getChildAt(0) as? TextView)
                    tv?.setTextColor(ContextCompat.getColor(requireContext(), if (isSelected) R.color.sf_on_brand else R.color.sf_chip_text))
                }
            }
        }
        chipMap.forEach { (id, kat) ->
            view.opt<View>(id)?.setOnClickListener {
                updateCategoryChips(id)
                kategori = kat
                loadBarang()
            }
        }
        view.opt<View>("chip_stok_menipis")?.setOnClickListener {
            updateCategoryChips("chip_stok_menipis")
            kategori = null
            loadBarang()
        }
        loadBarang()
    }

    private fun loadBarang() = runApi { api ->
        val res = api.getBarang(search, kategori)
        val items = if (res.isSuccessful && res.body()?.success == true) {
            res.body()?.data?.items ?: emptyList()
        } else {
            emptyList()
        }
        adapter.updateData(items)
        val kosong = items.isEmpty()
        view?.opt<View>("layout_empty_barang")?.visibility = if (kosong) View.VISIBLE else View.GONE
        view?.opt<View>("empty_state_barang_staff")?.visibility = if (kosong) View.VISIBLE else View.GONE
    }
}

class BarangFragment : BaseBarangFragment(R.layout.fragment_barang)
class BarangStaffFragment : BaseBarangFragment(R.layout.fragment_barang_staff)

class LaporanFragment : Fragment(R.layout.fragment_laporan) {

    private var jenisEkspor = "stok"

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        view.opt<View>("btn_notifikasi_laporan")?.pasangKlik { go("notifikasiFragment") }

        // Tab kategori laporan (FR-16)
        val tabMap = mapOf(
            "tab_laporan_stok" to "stok",
            "tab_laporan_mutasi" to "mutasi",
            "tab_laporan_kedaluwarsa" to "kedaluwarsa",
            "tab_laporan_aktivitas" to "aktivitas",
        )
        fun updateTabs(selectedId: String) {
            tabMap.keys.forEach { id ->
                val v = view?.opt<TextView>(id) ?: return@forEach
                if (id == selectedId) {
                    v.setBackgroundResource(R.drawable.bg_segment_active)
                    v.setTextColor(ContextCompat.getColor(requireContext(), R.color.sf_brand))
                } else {
                    v.background = null
                    v.setTextColor(Color.parseColor("#6E8277"))
                }
            }
        }
        tabMap.forEach { (id, jenis) ->
            view.opt<View>(id)?.setOnClickListener {
                updateTabs(id)
                muatLaporan(jenis)
            }
        }

        // Ekspor berkas xlsx dari tab aktif (khusus pemilik, BR-08)
        view.opt<View>("btn_export_csv")?.setOnClickListener { unduhLaporan(jenisEkspor) }

        muatLaporan("stok")
    }

    private fun muatLaporan(jenis: String) {
        jenisEkspor = jenis
        val v = view ?: return

        runApi { api ->
            when (jenis) {
                "stok" -> {
                    val res = api.getLaporanStok()
                    val rows = res.body()?.data?.rows ?: emptyList()
                    // baris: [kode_sku, nama, kategori, satuan, minimum, tersedia, status]
                    val adapter = SimpleCardAdapter(rows, mapper = { row ->
                        val status = (row.getOrNull(6) ?: "-").toString()
                        val warna = if (status == "Stok Menipis") R.color.sf_danger else R.color.sf_safe
                        SimpleCardAdapter.RowModel(
                            title = (row.getOrNull(1) ?: "-").toString(),
                            sub = "SKU ${(row.getOrNull(0) ?: "-")} · min ${(row.getOrNull(4) ?: 0)} ${(row.getOrNull(3) ?: "")}",
                            badge = "${row.getOrNull(5) ?: 0} ${(row.getOrNull(3) ?: "")} · $status",
                            colorRes = warna,
                        )
                    })
                    pasangList(v, adapter)
                    v.opt<TextView>("tv_stat_barang_aktif")?.text = rows.size.toString()
                    v.opt<TextView>("tv_stat_unit_tersedia")?.text =
                        rows.sumOf { (it.getOrNull(5) as? Number)?.toInt() ?: 0 }.toString()
                    v.opt<TextView>("tv_stat_stok_menipis")?.text =
                        rows.count { (it.getOrNull(6) ?: "").toString() == "Stok Menipis" }.toString()
                }

                "mutasi" -> {
                    val res = api.getMutasiStok()
                    if (res.isSuccessful && res.body()?.success == true) {
                        pasangList(v, MutasiAdapter(res.body()?.data?.items ?: emptyList()))
                    }
                }

                "kedaluwarsa" -> {
                    val res = api.getLaporanKedaluwarsa()
                    val rows = res.body()?.data?.rows ?: emptyList()
                    // baris: [kode_sku, nama, satuan, nomor_batch, sisa, tanggal, status, penanganan]
                    val adapter = SimpleCardAdapter(rows, mapper = { row ->
                        val status = (row.getOrNull(6) ?: "-").toString()
                        val warna = when (status) {
                            "Kedaluwarsa" -> R.color.sf_danger
                            "Mendekati Kedaluwarsa" -> R.color.sf_warn
                            else -> R.color.sf_muted
                        }
                        val badge = if (row.getOrNull(7) != null && (row.getOrNull(7) ?: "-").toString() != "-") {
                            "Ditangani (${row.getOrNull(7)})"
                        } else {
                            status
                        }
                        SimpleCardAdapter.RowModel(
                            title = "${row.getOrNull(1) ?: "-"} · ${row.getOrNull(3) ?: "-"}",
                            sub = "Sisa ${row.getOrNull(4) ?: 0} ${(row.getOrNull(2) ?: "")} · kedaluwarsa ${row.getOrNull(5) ?: "-"}",
                            badge = badge,
                            colorRes = warna,
                        )
                    })
                    pasangList(v, adapter)
                }

                else -> {
                    val res = api.getRiwayatAktivitas()
                    if (res.isSuccessful && res.body()?.success == true) {
                        pasangList(v, RiwayatAdapter(res.body()?.data?.items ?: emptyList()))
                    }
                }
            }
        }
    }

    private fun pasangList(v: View, adapter: RecyclerView.Adapter<*>) {
        v.opt<RecyclerView>("rv_laporan")?.apply {
            layoutManager = LinearLayoutManager(context)
            this.adapter = adapter
        }
    }

    private fun unduhLaporan(jenis: String) {
        // Kontrak backend ReportRequest: jenis ∈ {stok, transaksi, kedaluwarsa, laris}.
        // Tab UI memakai nama sendiri → petakan; tab aktivitas (audit) tidak punya ekspor di kontrak.
        val jenisEkspor = when (jenis) {
            "mutasi" -> "transaksi"
            "aktivitas" -> ""
            else -> jenis
        }
        if (jenisEkspor.isEmpty()) {
            Toast.makeText(requireContext(), "Ekspor tersedia untuk laporan stok, mutasi, dan kedaluwarsa", Toast.LENGTH_LONG).show()
            return
        }
        runApi { api ->
            val res = api.unduhLaporan(jenisEkspor, "xlsx")
            val isi = res.body()?.bytes()
            if (res.isSuccessful && isi != null && isi.isNotEmpty()) {
                val folder = requireContext().getExternalFilesDir(null)?.resolve("laporan")?.apply { mkdirs() }
                    ?: return@runApi
                val berkas = File(folder, "laporan-$jenisEkspor-${System.currentTimeMillis()}.xlsx")
                berkas.writeBytes(isi)
                val uri = FileProvider.getUriForFile(requireContext(), "${requireContext().packageName}.fileprovider", berkas)
                val bagikan = Intent(Intent.ACTION_SEND).apply {
                    type = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    putExtra(Intent.EXTRA_STREAM, uri)
                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                }
                startActivity(Intent.createChooser(bagikan, "Bagikan laporan $jenis"))
            } else {
                Toast.makeText(requireContext(), uraikanGagal(res, "Gagal mengunduh laporan (khusus pemilik)"), Toast.LENGTH_LONG).show()
            }
        }
    }
}

class RiwayatFragment : Fragment(R.layout.fragment_riwayat) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        val adapter = RiwayatAdapter(emptyList())
        view.opt<View>("btn_notifikasi_riwayat")?.pasangKlik { go("notifikasiFragment") }
        view.bindList("rv_riwayat", adapter)
        runApi { api ->
            val res = api.getRiwayatAktivitas()
            val items = if (res.isSuccessful && res.body()?.success == true) {
                res.body()?.data?.items ?: emptyList()
            } else {
                emptyList()
            }
            adapter.updateData(items)
            view?.opt<View>("empty_state_riwayat")?.visibility =
                if (items.isEmpty()) View.VISIBLE else View.GONE
        }
    }
}

class ProfilFragment : Fragment(R.layout.fragment_profil) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        val session = SessionManager(requireContext())
        val isOwner = session.getPeran() == "pemilik"

        view.opt<TextView>("tv_profil_nama")?.text = session.getNama().ifEmpty { "Pengguna" }
        view.opt<TextView>("tv_profil_username")?.text = "@${session.getUsername()}"
        view.opt<TextView>("tv_profil_peran")?.text = if (isOwner) "Pemilik Gudang" else "Staf Gudang"

        // Segarkan profil dari server (GET /me — FR-02) supaya data terkini.
        runApi { api ->
            val res = api.getMe()
            val u = res.body()?.data ?: return@runApi
            if (res.isSuccessful && res.body()?.success == true) {
                session.getToken()?.let { token -> session.saveSession(token, u.id, u.nama, u.username, u.peran) }
                view.opt<TextView>("tv_profil_nama")?.text = u.nama
                view.opt<TextView>("tv_profil_username")?.text = "@${u.username}"
                view.opt<TextView>("tv_profil_peran")?.text = if (u.peran == "pemilik") "Pemilik Gudang" else "Staf Gudang"
            }
        }

        val menuPengguna = view.opt<View>("menu_pengguna")
        val menuPengaturan = view.opt<View>("menu_pengaturan")
        menuPengguna?.visibility = if (isOwner) View.VISIBLE else View.GONE
        menuPengaturan?.visibility = if (isOwner) View.VISIBLE else View.GONE

        menuPengguna?.setOnClickListener { go("penggunaFragment") }
        menuPengaturan?.setOnClickListener { go("pengaturanFragment") }
        view.opt<View>("menu_data_skema")?.setOnClickListener { go("dataSkemaFragment") }

        view.opt<View>("btn_logout")?.setOnClickListener {
            viewLifecycleOwner.lifecycleScope.launch {
                runCatching { RetrofitClient.getInstance(requireContext()).logout() }
                session.clearSession()
                startActivity(Intent(requireContext(), LoginActivity::class.java).apply {
                    flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
                })
            }
        }
    }
}

class DetailBarangFragment : Fragment(R.layout.fragment_detail_barang) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        val barangId = arguments?.getInt("barangId") ?: -1

        view.opt<View>("btn_back")?.setOnClickListener { findNavController().navigateUp() }
        val argsBarang = if (barangId != -1) bundleOf("barangId" to barangId) else null
        view.opt<View>("btn_tambah_batch")?.setOnClickListener { go("formBatchFragment", argsBarang) }
        view.opt<View>("btn_link_tambah_batch")?.setOnClickListener { go("formBatchFragment", argsBarang) }
        view.opt<View>("btn_stok_keluar")?.setOnClickListener { go("formStokKeluarFragment", argsBarang) }

        val batchAdapter = BatchAdapter(emptyList()) { go("detailBatchFragment", bundleOf("batchId" to it.id)) }
        view.bindList("rv_batch", batchAdapter)

        view.opt<View>("btn_hapus_barang")?.setOnClickListener {
            if (barangId == -1) return@setOnClickListener
            AlertDialog.Builder(requireContext())
                .setTitle("Hapus Barang")
                .setMessage("Yakin ingin menghapus barang ini?")
                .setPositiveButton("Hapus") { _, _ ->
                    runApi { api ->
                        val res = api.deleteBarang(barangId)
                        if (res.isSuccessful && res.body()?.success == true) {
                            Toast.makeText(requireContext(), "Barang dihapus", Toast.LENGTH_SHORT).show()
                            findNavController().navigateUp()
                        } else {
                            Toast.makeText(requireContext(), uraikanGagal(res, "Gagal menghapus"), Toast.LENGTH_LONG).show()
                        }
                    }
                }
                .setNegativeButton("Batal", null)
                .show()
        }

        if (barangId != -1) {
            runApi { api ->
                api.getDetailBarang(barangId).body()?.data?.let { b ->
                    view.opt<TextView>("tv_nama_barang")?.text = b.nama
                    view.findViewById<TextView>(R.id.meta_sku).text = b.kode_sku
                    view.findViewById<TextView>(R.id.meta_kategori).text = b.kategori
                    view.findViewById<TextView>(R.id.meta_satuan).text = b.satuan
                    view.opt<TextView>("tv_total_stok")?.text = "${b.total_stok_tersedia} ${b.satuan}"
                    view.opt<TextView>("tv_stok_minimum")?.text = "${b.stok_minimum} ${b.satuan}"
                }
                val batchRes = api.getBatchStok(barangId = barangId)
                if (batchRes.isSuccessful && batchRes.body()?.success == true) {
                    batchAdapter.updateData((batchRes.body()?.data?.items ?: emptyList()).sortedBy { it.tanggal_kedaluwarsa.orEmpty() })
                }
            }
        }
    }
}

class DetailBatchFragment : Fragment(R.layout.fragment_detail_batch) {

    private var currentBatch: BatchStokItem? = null

    private val pickFoto = registerForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        val b = currentBatch ?: return@registerForActivityResult
        if (uri != null) unggahFoto(uri, b.id)
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        val batchId = arguments?.getInt("batchId") ?: -1
        view.opt<View>("btn_back")?.setOnClickListener { findNavController().navigateUp() }
        view.opt<View>("btn_more_batch")?.setOnClickListener { tampilkanMenuAksi(batchId) }
        view.opt<View>("btn_tambah_foto")?.setOnClickListener { pickFoto.launch("image/*") }

        view.opt<View>("btn_penyesuaian")?.setOnClickListener {
            val b = currentBatch ?: return@setOnClickListener
            go("formPenyesuaianFragment", bundleOf("batchId" to b.id, "jumlahSisa" to b.jumlah_sisa, "barangId" to b.barang_id))
        }

        view.opt<View>("btn_tangani_batch")?.setOnClickListener {
            if (batchId == -1) return@setOnClickListener
            val opsi = arrayOf("Dimusnahkan", "Dikembalikan ke Pemasok", "Lainnya")
            AlertDialog.Builder(requireContext())
                .setTitle("Tindakan Kedaluwarsa")
                .setItems(opsi) { _, i ->
                    runApi { api ->
                        val res = api.tanganiKedaluwarsa(
                            PenangananRequest(batchId, opsi[i], null, "Ditangani melalui aplikasi (${opsi[i]})"),
                        )
                        if (res.isSuccessful && res.body()?.success == true) {
                            Toast.makeText(requireContext(), "Batch berhasil ditangani", Toast.LENGTH_SHORT).show()
                            muatDetail(batchId, view)
                        } else {
                            Toast.makeText(requireContext(), uraikanGagal(res, "Gagal memproses penanganan"), Toast.LENGTH_LONG).show()
                        }
                    }
                }
                .show()
        }

        if (batchId != -1) muatDetail(batchId, view)
    }

    private fun muatDetail(batchId: Int, view: View) = runApi { api ->
        val b = api.getDetailBatch(batchId).body()?.data ?: return@runApi
        currentBatch = b
        view.opt<TextView>("tv_nomor_batch")?.text = b.nomor_batch
        view.opt<TextView>("tv_nama_barang")?.text = b.nama_barang ?: "Barang #${b.barang_id}"
        view.opt<TextView>("tv_jumlah_sisa")?.text = "${b.jumlah_sisa} / ${b.jumlah_awal}"
        view.opt<TextView>("tv_tanggal_kedaluwarsa")?.text = b.tanggal_kedaluwarsa
        view.opt<TextView>("tv_status_batch")?.text = b.status
        view.opt<TextView>("tv_label_sisa_rel")?.text = labelRelatif(b.tanggal_kedaluwarsa)
    }

    // ── Menu aksi tambahan (ikon ⋮ di topbar) ─────────────────────────────
    private fun tampilkanMenuAksi(batchId: Int) {
        val b = currentBatch
        val opsi = arrayOf("Tambah stok ke batch (masuk)", "Ubah karakteristik batch (BR-01)", "Hapus batch")
        AlertDialog.Builder(requireContext())
            .setTitle("Aksi batch ${b?.nomor_batch ?: "#$batchId"}")
            .setItems(opsi) { _, i ->
                when (i) {
                    0 -> dialogStokMasuk(b)
                    1 -> dialogUbahKarakteristik(b)
                    else -> dialogHapusBatch(batchId)
                }
            }
            .show()
    }

    private fun dialogStokMasuk(b: BatchStokItem?) {
        if (b == null) return
        val input = EditText(requireContext()).apply {
            inputType = InputType.TYPE_CLASS_NUMBER
            hint = "Jumlah masuk"
        }
        AlertDialog.Builder(requireContext())
            .setTitle("Tambah stok — ${b.nomor_batch}")
            .setView(input)
            .setPositiveButton("Simpan") { _, _ ->
                val jumlah = input.text.toString().trim().toIntOrNull()
                if (jumlah == null || jumlah <= 0) {
                    Toast.makeText(requireContext(), "Jumlah wajib angka lebih dari 0", Toast.LENGTH_SHORT).show()
                    return@setPositiveButton
                }
                runApi { api ->
                    val res = api.storeStokKeluar(
                        TransaksiRequest(
                            jenis = "masuk",
                            barang_id = b.barang_id,
                            jumlah = jumlah,
                            catatan = "Stok masuk ke batch ${b.nomor_batch}",
                            batch_id = b.id,
                        ),
                    )
                    if (res.isSuccessful && res.body()?.success == true) {
                        Toast.makeText(requireContext(), "Stok masuk tercatat", Toast.LENGTH_SHORT).show()
                        muatDetail(b.id, requireView())
                    } else {
                        Toast.makeText(requireContext(), uraikanGagal(res, "Gagal mencatat stok masuk"), Toast.LENGTH_LONG).show()
                    }
                }
            }
            .setNegativeButton("Batal", null)
            .show()
    }

    // BR-01: hanya nomor_batch & tanggal_kedaluwarsa yang boleh diubah.
    private fun dialogUbahKarakteristik(b: BatchStokItem?) {
        if (b == null) return
        var tanggalBaru = b.tanggal_kedaluwarsa ?: ""

        val kontainer = LinearLayout(requireContext()).apply {
            orientation = LinearLayout.VERTICAL
            val pad = (16 * resources.displayMetrics.density).toInt()
            setPadding(pad, pad, pad, 0)
        }
        val etNomor = EditText(requireContext()).apply { setText(b.nomor_batch) }
        val pilihTanggal = TextView(requireContext()).apply {
            text = "Kedaluwarsa: $tanggalBaru (ketuk untuk ubah)"
            setPadding(0, 8, 0, 0)
            setOnClickListener {
                val kal = Calendar.getInstance()
                val bagian = tanggalBaru.split("-")
                if (bagian.size == 3) {
                    runCatching { kal.set(bagian[0].toInt(), bagian[1].toInt() - 1, bagian[2].toInt()) }
                }
                DatePickerDialog(
                    requireContext(),
                    { _, y, m, d ->
                        tanggalBaru = String.format(Locale.US, "%04d-%02d-%02d", y, m + 1, d)
                        text = "Kedaluwarsa: $tanggalBaru (ketuk untuk ubah)"
                    },
                    kal.get(Calendar.YEAR),
                    kal.get(Calendar.MONTH),
                    kal.get(Calendar.DAY_OF_MONTH),
                ).show()
            }
        }
        kontainer.addView(etNomor)
        kontainer.addView(pilihTanggal)

        AlertDialog.Builder(requireContext())
            .setTitle("Ubah karakteristik batch (BR-01)")
            .setView(kontainer)
            .setPositiveButton("Simpan") { _, _ ->
                val nomor = etNomor.text.toString().trim()
                if (nomor.isEmpty() || tanggalBaru.isEmpty()) {
                    Toast.makeText(requireContext(), "Nomor batch dan tanggal wajib diisi", Toast.LENGTH_SHORT).show()
                    return@setPositiveButton
                }
                runApi { api ->
                    val res = api.updateBatch(b.id, BatchUpdateRequest(nomor_batch = nomor, tanggal_kedaluwarsa = tanggalBaru))
                    if (res.isSuccessful && res.body()?.success == true) {
                        Toast.makeText(requireContext(), "Karakteristik batch diperbarui", Toast.LENGTH_SHORT).show()
                        muatDetail(b.id, requireView())
                    } else {
                        Toast.makeText(requireContext(), uraikanGagal(res, "Gagal mengubah batch"), Toast.LENGTH_LONG).show()
                    }
                }
            }
            .setNegativeButton("Batal", null)
            .show()
    }

    private fun dialogHapusBatch(batchId: Int) {
        if (batchId == -1) return
        AlertDialog.Builder(requireContext())
            .setTitle("Hapus batch")
            .setMessage("Batch akan dihapus permanen. Yakin?")
            .setPositiveButton("Hapus") { _, _ ->
                runApi { api ->
                    val res = api.deleteBatch(batchId)
                    if (res.isSuccessful && res.body()?.success == true) {
                        Toast.makeText(requireContext(), "Batch dihapus", Toast.LENGTH_SHORT).show()
                        findNavController().navigateUp()
                    } else {
                        Toast.makeText(requireContext(), uraikanGagal(res, "Gagal menghapus batch"), Toast.LENGTH_LONG).show()
                    }
                }
            }
            .setNegativeButton("Batal", null)
            .show()
    }

    // FR-14: unggah foto bukti batch (JPG/PNG).
    private fun unggahFoto(uri: Uri, batchId: Int) {
        runApi { api ->
            val stream = requireContext().contentResolver.openInputStream(uri) ?: return@runApi
            val file = File(requireContext().cacheDir, "bukti_batch_$batchId.jpg")
            file.outputStream().use { stream.copyTo(it) }
            val filePart = MultipartBody.Part.createFormData("lampiran", file.name, file.asRequestBody("image/jpeg".toMediaTypeOrNull()))
            val jenisPart = MultipartBody.Part.createFormData("jenis", "bukti_batch")
            val res = api.uploadLampiran(batchId, filePart, jenisPart)
            if (res.isSuccessful && res.body()?.success == true) {
                Toast.makeText(requireContext(), "Foto bukti diunggah", Toast.LENGTH_SHORT).show()
            } else {
                Toast.makeText(requireContext(), uraikanGagal(res, "Gagal mengunggah foto"), Toast.LENGTH_LONG).show()
            }
        }
    }

    private fun labelRelatif(tanggal: String?): String {
        val selisih = selisihHari(tanggal) ?: return tanggal ?: "-"
        return when {
            selisih < 0 -> "Kedaluwarsa ${-selisih} hr lalu"
            selisih == 0L -> "Kedaluwarsa hari ini"
            else -> "$selisih hr lagi"
        }
    }

    private fun selisihHari(tanggal: String?): Long? = runCatching {
        val pola = SimpleDateFormat("yyyy-MM-dd", Locale.US).apply { isLenient = false }
        val tgl = pola.parse(tanggal ?: error("tanggal kosong")) ?: error("format tanggal")
        fun tengahMalam(d: Date): Calendar =
            Calendar.getInstance().apply {
                time = d
                set(Calendar.HOUR_OF_DAY, 0)
                set(Calendar.MINUTE, 0)
                set(Calendar.SECOND, 0)
                set(Calendar.MILLISECOND, 0)
            }
        TimeUnit.MILLISECONDS.toDays(tengahMalam(tgl).timeInMillis - tengahMalam(Date()).timeInMillis)
    }.getOrNull()
}

class PerluDitanganiFragment : Fragment(R.layout.fragment_perlu_ditangani) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        view.opt<View>("btn_back")?.setOnClickListener { findNavController().navigateUp() }
        view.opt<View>("btn_back_perlu_ditangani")?.setOnClickListener { findNavController().navigateUp() }
        view.opt<View>("btn_notifikasi_ditangani")?.pasangKlik { go("notifikasiFragment") }
        val adapter = BatchAdapter(emptyList()) { go("detailBatchFragment", bundleOf("batchId" to it.id)) }
        view.bindList("rv_perlu_ditangani", adapter)
        runApi { api ->
            val res = api.getBatchStok(status = "Kedaluwarsa")
            val items = if (res.isSuccessful && res.body()?.success == true) {
                res.body()?.data?.items ?: emptyList()
            } else {
                emptyList()
            }
            adapter.updateData(items)
            view?.opt<View>("empty_state_perlu_ditangani")?.visibility =
                if (items.isEmpty()) View.VISIBLE else View.GONE
        }
    }
}

class NotifikasiFragment : Fragment(R.layout.fragment_notifikasi) {

    private lateinit var adapter: NotifikasiAdapter
    private var jenisAktif: String? = null

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        view.opt<View>("btn_back")?.setOnClickListener { findNavController().navigateUp() }
        view.opt<View>("btn_back_notifikasi")?.setOnClickListener { findNavController().navigateUp() }
        adapter = NotifikasiAdapter(emptyList()) { n ->
            when {
                n.batch_id != null -> go("detailBatchFragment", bundleOf("batchId" to n.batch_id))
                n.barang_id != null -> go("detailBarangFragment", bundleOf("barangId" to n.barang_id))
            }
        }
        view.bindList("rv_notifikasi", adapter)

        val notifChips = mapOf(
            "chip_notif_semua" to null,
            "chip_notif_mendekati" to "Mendekati Kedaluwarsa",
            "chip_notif_kedaluwarsa" to "Kedaluwarsa",
            "chip_notif_menipis" to "Stok Menipis",
        )
        fun updateNotifChips(selectedId: String) {
            notifChips.keys.forEach { id ->
                val v = view.opt<TextView>(id) ?: return@forEach
                if (id == selectedId) {
                    v.setBackgroundResource(R.drawable.bg_chip_active)
                    v.setTextColor(ContextCompat.getColor(requireContext(), R.color.sf_on_brand))
                } else {
                    v.setBackgroundResource(R.drawable.bg_chip)
                    v.setTextColor(ContextCompat.getColor(requireContext(), R.color.sf_chip_text))
                }
            }
        }
        notifChips.forEach { (id, nilai) ->
            view.opt<View>(id)?.setOnClickListener {
                updateNotifChips(id)
                jenisAktif = nilai
                muatNotifikasi()
            }
        }

        view.opt<View>("btn_mark_all_read")?.setOnClickListener {
            runApi { api ->
                val res = api.tandaiSemuaNotifikasiDibaca()
                if (res.isSuccessful && res.body()?.success == true) {
                    Toast.makeText(requireContext(), "Semua notifikasi ditandai dibaca", Toast.LENGTH_SHORT).show()
                    muatNotifikasi()
                } else {
                    Toast.makeText(requireContext(), uraikanGagal(res, "Gagal menandai notifikasi"), Toast.LENGTH_LONG).show()
                }
            }
        }

        muatNotifikasi()
    }

    private fun muatNotifikasi() = runApi { api ->
        val res = api.getNotifikasi(jenis = jenisAktif)
        val items = if (res.isSuccessful && res.body()?.success == true) {
            res.body()?.data?.items ?: emptyList()
        } else {
            emptyList()
        }
        adapter.updateData(items)
        val v = view ?: return@runApi
        v.opt<TextView>("tv_count_unread")?.text = items.count { it.ditampilkan_pada == null }.toString()
        v.opt<View>("empty_state_notifikasi")?.visibility = if (items.isEmpty()) View.VISIBLE else View.GONE
    }
}

class PenggunaFragment : Fragment(R.layout.fragment_pengguna) {

    private lateinit var adapter: UserAdapter

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        view.opt<View>("btn_back_pengguna")?.setOnClickListener { findNavController().navigateUp() }
        view.opt<View>("btn_tambah_pengguna")?.setOnClickListener { go("formAkunFragment") }

        adapter = UserAdapter(emptyList()) { user ->
            val aktifBaru = !user.aktif
            AlertDialog.Builder(requireContext())
                .setTitle("Ubah Status Akun")
                .setMessage("Yakin ingin me-${if (aktifBaru) "aktifkan" else "nonaktifkan"} akun ${user.nama}?")
                .setPositiveButton("Ya") { _, _ ->
                    runApi { api ->
                        val res = api.ubahAkunAktif(user.id, AktifRequest(aktifBaru))
                        if (res.isSuccessful && res.body()?.success == true) {
                            Toast.makeText(requireContext(), "Status akun diperbarui", Toast.LENGTH_SHORT).show()
                            muatUsers()
                        } else {
                            Toast.makeText(requireContext(), uraikanGagal(res, "Gagal mengubah status"), Toast.LENGTH_LONG).show()
                        }
                    }
                }
                .setNegativeButton("Batal", null)
                .show()
        }
        view.bindList("rv_pengguna", adapter)
        muatUsers()
    }

    private fun muatUsers() = runApi { api ->
        val res = api.getUsers()
        val items = if (res.isSuccessful && res.body()?.success == true) {
            res.body()?.data?.items ?: emptyList()
        } else {
            emptyList()
        }
        adapter.updateData(items)
        val v = view ?: return@runApi
        v.opt<TextView>("tv_stat_total_akun")?.text = items.size.toString()
        v.opt<TextView>("tv_stat_aktif_akun")?.text = items.count { it.aktif }.toString()
        v.opt<TextView>("tv_stat_staf_akun")?.text = items.count { it.peran == "staf" }.toString()
        v.opt<View>("empty_state_pengguna")?.visibility = if (items.isEmpty()) View.VISIBLE else View.GONE
    }
}

class PengaturanFragment : Fragment(R.layout.fragment_pengaturan) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        view.opt<View>("btn_back")?.setOnClickListener { findNavController().navigateUp() }
        view.opt<View>("btn_batal")?.setOnClickListener { findNavController().navigateUp() }

        val etAmbang = view.opt<EditText>("et_ambang_hari")
        val etLampiran = view.opt<EditText>("et_maks_lampiran_mb")
        val etBarisImpor = view.opt<EditText>("et_maks_baris_impor")

        runApi { api ->
            val data = api.getPengaturan().body()?.data ?: return@runApi
            fun ambil(kunci: String): String? = data.find { it.kunci == kunci }?.nilai
            ambil("ambang_peringatan_hari")?.let { etAmbang?.setText(it) }
            ambil("maks_ukuran_lampiran_mb")?.let { etLampiran?.setText(it) }
            ambil("maks_baris_impor")?.let { etBarisImpor?.setText(it) }
        }

        view.opt<View>("btn_simpan_pengaturan")?.setOnClickListener {
            val ambang = etAmbang?.text?.toString()?.trim()?.toIntOrNull()
            val maksLampiran = etLampiran?.text?.toString()?.trim()?.toIntOrNull()
            val maksBaris = etBarisImpor?.text?.toString()?.trim()?.toIntOrNull()
            if (ambang == null || maksLampiran == null || maksBaris == null) {
                Toast.makeText(requireContext(), "Seluruh kolom wajib berupa angka", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            runApi { api ->
                val res = api.updatePengaturan(
                    mapOf(
                        "ambang_peringatan_hari" to ambang,
                        "maks_ukuran_lampiran_mb" to maksLampiran,
                        "maks_baris_impor" to maksBaris,
                    ),
                )
                if (res.isSuccessful && res.body()?.success == true) {
                    Toast.makeText(requireContext(), "Pengaturan disimpan", Toast.LENGTH_SHORT).show()
                    findNavController().navigateUp()
                } else {
                    Toast.makeText(requireContext(), uraikanGagal(res, "Gagal menyimpan pengaturan"), Toast.LENGTH_LONG).show()
                }
            }
        }
    }
}

class DataSkemaFragment : Fragment(R.layout.fragment_data_skema) {
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        view.opt<View>("btn_back")?.setOnClickListener { findNavController().navigateUp() }
    }
}

class AksesDitolakFragment : Fragment(R.layout.fragment_akses_ditolak) {
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        view.opt<View>("btn_kembali")?.setOnClickListener { findNavController().navigateUp() }
    }
}

class TidakDitemukanFragment : Fragment(R.layout.fragment_tidak_ditemukan) {
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        view.opt<View>("btn_kembali")?.setOnClickListener { findNavController().navigateUp() }
    }
}