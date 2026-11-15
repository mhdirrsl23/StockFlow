package com.example.stockflow

import android.app.AlertDialog
import android.content.Intent
import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.View
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.core.os.bundleOf
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import kotlinx.coroutines.launch

private inline fun <reified T : View> View.opt(idName: String): T? {
    val id = resources.getIdentifier(idName, "id", context.packageName)
    return if (id != 0) findViewById(id) else null
}

private fun Fragment.go(destName: String, args: Bundle? = null) {
    val id = resources.getIdentifier(destName, "id", requireContext().packageName)
    if (id != 0) runCatching { findNavController().navigate(id, args) }
}

private fun Fragment.runApi(block: suspend (ApiService) -> Unit) {
    viewLifecycleOwner.lifecycleScope.launch {
        runCatching { block(RetrofitClient.getInstance(requireContext())) }
    }
}

private fun <VH : RecyclerView.ViewHolder> View.bindList(rvId: String, adapter: RecyclerView.Adapter<VH>) {
    opt<RecyclerView>(rvId)?.apply {
        layoutManager = LinearLayoutManager(context)
        this.adapter = adapter
    }
}

open class BaseBerandaFragment(layoutId: Int, private val fallbackNama: String) : Fragment(layoutId) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        val nama = SessionManager(requireContext()).getNama().ifEmpty { fallbackNama }
        view.opt<TextView>("tv_sapaan")?.text = "Halo, $nama"

        mapOf(
            "btn_notifikasi" to "notifikasiFragment",
            "btn_perlu_ditangani" to "perluDitanganiFragment",
            "btn_tambah_barang" to "formBarangFragment",
            "btn_tambah_batch" to "formBatchFragment",
            "btn_stok_keluar" to "formStokKeluarFragment",
            "btn_lihat_semua_barang" to "nav_barang",
            "btn_lihat_laporan" to "nav_laporan",
            "btn_lihat_riwayat" to "nav_riwayat"
        ).forEach { (btnId, dest) ->
            view.opt<View>(btnId)?.setOnClickListener { go(dest) }
        }

        runApi { api ->
            val d = api.getDashboard().body()?.data ?: return@runApi
            view.opt<TextView>("tv_total_barang")?.text = d.total_barang.toString()
            view.opt<TextView>("tv_total_stok")?.text = d.total_stok.toString()
            view.opt<TextView>("tv_stok_menipis")?.text = d.stok_menipis.toString()
            view.opt<TextView>("tv_mendekati_kedaluwarsa")?.text = d.mendekati_kedaluwarsa.toString()
            view.opt<TextView>("tv_kedaluwarsa")?.text = d.kedaluwarsa.toString()
            view.opt<TextView>("tv_perlu_ditangani")?.text = d.perlu_ditangani.toString()
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

        mapOf(
            "chip_semua" to null,
            "chip_makanan" to "Makanan",
            "chip_minuman" to "Minuman",
            "chip_obat" to "Obat",
            "chip_rumah" to "Kebutuhan Rumah",
            "chip_lainnya" to "Lainnya"
        ).forEach { (id, kat) ->
            view.opt<View>(id)?.setOnClickListener {
                kategori = kat
                loadBarang()
            }
        }
        loadBarang()
    }

    private fun loadBarang() = runApi { api ->
        val res = api.getBarang(search, kategori)
        if (res.isSuccessful && res.body()?.status == true) {
            adapter.updateData(res.body()?.data ?: emptyList())
        }
    }
}

class BarangFragment : BaseBarangFragment(R.layout.fragment_barang)
class BarangStaffFragment : BaseBarangFragment(R.layout.fragment_barang_staff)

class LaporanFragment : Fragment(R.layout.fragment_laporan) {

    private lateinit var adapter: MutasiAdapter
    private var allMutasi: List<MutasiStokItem> = emptyList()

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        adapter = MutasiAdapter(emptyList())
        view.bindList("rv_laporan", adapter)
        view.opt<View>("btn_riwayat_aktivitas")?.setOnClickListener { go("nav_riwayat") }

        mapOf(
            "chip_semua" to null,
            "chip_masuk" to "masuk",
            "chip_keluar" to "keluar",
            "chip_penyesuaian" to "penyesuaian",
            "chip_penanganan" to "penanganan"
        ).forEach { (id, jenis) ->
            view.opt<View>(id)?.setOnClickListener {
                adapter.updateData(if (jenis == null) allMutasi else allMutasi.filter { it.jenis == jenis })
            }
        }

        runApi { api ->
            val res = api.getMutasiStok()
            if (res.isSuccessful && res.body()?.status == true) {
                allMutasi = res.body()?.data ?: emptyList()
                adapter.updateData(allMutasi)
            }
        }
    }
}

class RiwayatFragment : Fragment(R.layout.fragment_riwayat) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        val adapter = RiwayatAdapter(emptyList())
        view.bindList("rv_riwayat", adapter)
        runApi { api ->
            val res = api.getRiwayatAktivitas()
            if (res.isSuccessful && res.body()?.status == true) adapter.updateData(res.body()?.data ?: emptyList())
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
        view.opt<View>("btn_tambah_batch")?.setOnClickListener { go("formBatchFragment") }
        view.opt<View>("btn_stok_keluar")?.setOnClickListener { go("formStokKeluarFragment") }

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
                        if (res.isSuccessful && res.body()?.status == true) {
                            Toast.makeText(requireContext(), "Barang dihapus", Toast.LENGTH_SHORT).show()
                            findNavController().navigateUp()
                        } else {
                            Toast.makeText(requireContext(), res.body()?.message ?: "Gagal menghapus", Toast.LENGTH_SHORT).show()
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
                if (batchRes.isSuccessful && batchRes.body()?.status == true) {
                    batchAdapter.updateData((batchRes.body()?.data ?: emptyList()).sortedBy { it.tanggal_kedaluwarsa })
                }
            }
        }
    }
}

class DetailBatchFragment : Fragment(R.layout.fragment_detail_batch) {

    private var currentBatch: BatchStokItem? = null

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        val batchId = arguments?.getInt("batchId") ?: -1
        view.opt<View>("btn_back")?.setOnClickListener { findNavController().navigateUp() }

        view.opt<View>("btn_penyesuaian")?.setOnClickListener {
            val b = currentBatch ?: return@setOnClickListener
            go("formPenyesuaianFragment", bundleOf("batchId" to b.id, "jumlahSisa" to b.jumlah_sisa))
        }

        view.opt<View>("btn_tangani_batch")?.setOnClickListener {
            if (batchId == -1) return@setOnClickListener
            val opsi = arrayOf("Dimusnahkan", "Dikembalikan ke Pemasok", "Lainnya")
            AlertDialog.Builder(requireContext())
                .setTitle("Tindakan Kedaluwarsa")
                .setItems(opsi) { _, i ->
                    runApi { api ->
                        val res = api.tanganiBatch(batchId, PenangananRequest(opsi[i], "Ditangani melalui aplikasi (${opsi[i]})"))
                        if (res.isSuccessful && res.body()?.status == true) {
                            Toast.makeText(requireContext(), "Batch berhasil ditangani", Toast.LENGTH_SHORT).show()
                            muatDetail(batchId, view)
                        } else {
                            Toast.makeText(requireContext(), res.body()?.message ?: "Gagal memproses penanganan", Toast.LENGTH_SHORT).show()
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
        view.opt<TextView>("tv_nama_barang")?.text = b.barang?.nama ?: "Barang #${b.barang_id}"
        view.opt<TextView>("tv_jumlah_sisa")?.text = "${b.jumlah_sisa} / ${b.jumlah_awal}"
        view.opt<TextView>("tv_tanggal_kedaluwarsa")?.text = b.tanggal_kedaluwarsa
        view.opt<TextView>("tv_status_batch")?.text = b.status
    }
}

class PerluDitanganiFragment : Fragment(R.layout.fragment_perlu_ditangani) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        view.opt<View>("btn_back")?.setOnClickListener { findNavController().navigateUp() }
        val adapter = BatchAdapter(emptyList()) { go("detailBatchFragment", bundleOf("batchId" to it.id)) }
        view.bindList("rv_perlu_ditangani", adapter)
        runApi { api ->
            val res = api.getBatchStok(status = "Kedaluwarsa")
            if (res.isSuccessful && res.body()?.status == true) adapter.updateData(res.body()?.data ?: emptyList())
        }
    }
}

class NotifikasiFragment : Fragment(R.layout.fragment_notifikasi) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        view.opt<View>("btn_back")?.setOnClickListener { findNavController().navigateUp() }
        val adapter = NotifikasiAdapter(emptyList()) { n ->
            when {
                n.batch_id != null -> go("detailBatchFragment", bundleOf("batchId" to n.batch_id))
                n.barang_id != null -> go("detailBarangFragment", bundleOf("barangId" to n.barang_id))
            }
        }
        view.bindList("rv_notifikasi", adapter)
        runApi { api ->
            val res = api.getNotifikasi()
            if (res.isSuccessful && res.body()?.status == true) adapter.updateData(res.body()?.data ?: emptyList())
        }
    }
}

class PenggunaFragment : Fragment(R.layout.fragment_pengguna) {

    private lateinit var adapter: UserAdapter

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        view.opt<View>("btn_back")?.setOnClickListener { findNavController().navigateUp() }
        view.opt<View>("btn_tambah_akun")?.setOnClickListener { go("formAkunFragment") }

        adapter = UserAdapter(emptyList()) { user ->
            val aktifBaru = if (user.aktif == 1) 0 else 1
            AlertDialog.Builder(requireContext())
                .setTitle("Ubah Status Akun")
                .setMessage("Yakin ingin me-${if (aktifBaru == 1) "aktifkan" else "nonaktifkan"} akun ${user.nama}?")
                .setPositiveButton("Ya") { _, _ ->
                    runApi { api ->
                        val res = api.updateUser(user.id, UserRequest(user.nama, user.username, null, user.peran, aktifBaru))
                        if (res.isSuccessful && res.body()?.status == true) {
                            Toast.makeText(requireContext(), "Status akun diperbarui", Toast.LENGTH_SHORT).show()
                            muatUsers()
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
        if (res.isSuccessful && res.body()?.status == true) adapter.updateData(res.body()?.data ?: emptyList())
    }
}

class PengaturanFragment : Fragment(R.layout.fragment_pengaturan) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        view.opt<View>("btn_back")?.setOnClickListener { findNavController().navigateUp() }
        val etAmbang = view.opt<EditText>("et_ambang_hari")

        runApi { api ->
            val ambang = api.getPengaturan().body()?.data?.find { it.kunci == "ambang_peringatan_hari" }?.nilai
            if (!ambang.isNullOrEmpty()) etAmbang?.setText(ambang)
        }

        view.opt<View>("btn_simpan_pengaturan")?.setOnClickListener {
            val nilai = etAmbang?.text?.toString()?.trim().orEmpty()
            if (nilai.isEmpty()) {
                Toast.makeText(requireContext(), "Ambang hari wajib diisi", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            runApi { api ->
                val res = api.updatePengaturan(mapOf("ambang_peringatan_hari" to nilai))
                if (res.isSuccessful && res.body()?.status == true) {
                    Toast.makeText(requireContext(), "Pengaturan disimpan", Toast.LENGTH_SHORT).show()
                    findNavController().navigateUp()
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