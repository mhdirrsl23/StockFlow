package com.example.stockflow

import android.app.AlertDialog
import android.app.DatePickerDialog
import android.net.Uri
import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.View
import android.view.ViewGroup
import android.widget.CompoundButton
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import com.google.gson.Gson
import com.google.gson.JsonObject
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import retrofit2.Response
import java.io.File
import java.io.IOException
import java.util.Calendar
import java.util.Locale

private fun Fragment.bindCloseButtons(view: View) {
    view.findViewById<View>(R.id.btn_back)?.setOnClickListener { findNavController().navigateUp() }
    view.findViewById<View>(R.id.btn_batal)?.setOnClickListener { findNavController().navigateUp() }
}

private fun View.updateSelectText(text: String) {
    if (this is TextView) {
        this.text = text
    } else if (this is ViewGroup) {
        for (i in 0 until childCount) {
            val child = getChildAt(i)
            if (child is TextView) {
                child.text = text
                break
            }
        }
    }
}

private fun Fragment.showPicker(title: String, items: Array<String>, target: View, onPick: (Int) -> Unit) {
    AlertDialog.Builder(requireContext())
        .setTitle(title)
        .setItems(items) { _, i ->
            target.updateSelectText(items[i])
            onPick(i)
        }
        .show()
}





private fun Fragment.uraikanGagal(res: Response<*>, bawaan: String): String {
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

private fun <T> Fragment.submitApi(
    successMsg: String,
    failMsg: String,
    call: suspend (ApiService) -> Response<ApiResponse<T>>,
    onSuccess: (suspend (ApiService, T?) -> Unit)? = null,
    tombol: View? = null,
) {
    viewLifecycleOwner.lifecycleScope.launch {
        tombol?.isEnabled = false
        tombol?.alpha = 0.6f
        try {
            val api = RetrofitClient.getInstance(requireContext())
            val res = call(api)
            if (res.isSuccessful && res.body()?.success == true) {
                onSuccess?.invoke(api, res.body()?.data)
                Toast.makeText(requireContext(), successMsg, Toast.LENGTH_SHORT).show()
                findNavController().navigateUp()
            } else {
                Toast.makeText(requireContext(), uraikanGagal(res, failMsg), Toast.LENGTH_LONG).show()
            }
        } catch (e: Exception) {
            Toast.makeText(requireContext(), "Error: ${e.message}", Toast.LENGTH_SHORT).show()
        } finally {
            tombol?.isEnabled = true
            tombol?.alpha = 1f
        }
    }
}

class FormBarangFragment : Fragment(R.layout.form_barang) {

    private var kategori = "Makanan"
    private val listKategori = arrayOf("Makanan", "Minuman", "Obat", "Kebutuhan Rumah", "Lainnya")

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        bindCloseButtons(view)

        val etSku = view.findViewById<EditText>(R.id.et_kode_sku)
        val etNama = view.findViewById<EditText>(R.id.et_nama)
        val selectKat = view.findViewById<View>(R.id.select_kategori)
        val etSatuan = view.findViewById<EditText>(R.id.et_satuan)
        val etMin = view.findViewById<EditText>(R.id.et_stok_minimum)

        selectKat.setOnClickListener { showPicker("Pilih Kategori", listKategori, selectKat) { kategori = listKategori[it] } }

        view.findViewById<View>(R.id.btn_simpan).setOnClickListener {
            val sku = etSku.text.toString().trim()
            val nama = etNama.text.toString().trim()
            val satuan = etSatuan.text.toString().trim()
            val min = etMin.text.toString().trim().toIntOrNull() ?: 0
            if (sku.isEmpty() || nama.isEmpty() || satuan.isEmpty()) {
                Toast.makeText(requireContext(), "SKU, nama barang, dan satuan wajib diisi", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            submitApi(
                "Barang berhasil disimpan",
                "Gagal menyimpan barang",
                { it.storeBarang(BarangRequest(sku, nama, kategori, satuan, min)) },
                tombol = view.findViewById(R.id.btn_simpan),
            )
        }
    }
}

class FormBatchFragment : Fragment(R.layout.form_batch) {

    private var barangList: List<BarangItem> = emptyList()
    private var barangId = -1
    private var jumlah = 1
    private var tanggal = ""
    private var imageUri: Uri? = null

    private val pickImage = registerForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        if (uri != null) {
            imageUri = uri
            view?.findViewById<TextView>(R.id.tv_status_foto_batch)?.text = "Foto batch dipilih"
            Toast.makeText(
                requireContext(),
                "Foto dipilih; foto akan diunggah saat batch disimpan",
                Toast.LENGTH_SHORT,
            ).show()
        }
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        bindCloseButtons(view)

        val selectBrg = view.findViewById<View>(R.id.select_barang)
        val etBatch = view.findViewById<EditText>(R.id.et_nomor_batch)
        val etJumlah = view.findViewById<EditText>(R.id.batch_jumlah)
        val selectTgl = view.findViewById<View>(R.id.select_tanggal)


        arguments?.getInt("barangId", -1)?.takeIf { it > 0 }?.let { barangId = it }

        jumlah = etJumlah?.text?.toString()?.trim()?.toIntOrNull() ?: 1
        etJumlah?.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                jumlah = s?.toString()?.trim()?.toIntOrNull() ?: 0
            }
        })
        view.findViewById<View>(R.id.btn_batch_minus).setOnClickListener {
            if (jumlah > 1) {
                jumlah--
                etJumlah?.setText(jumlah.toString())
                etJumlah?.setSelection(etJumlah.text.length)
            }
        }
        view.findViewById<View>(R.id.btn_batch_plus).setOnClickListener {
            jumlah++
            etJumlah?.setText(jumlah.toString())
            etJumlah?.setSelection(etJumlah.text.length)
        }

        viewLifecycleOwner.lifecycleScope.launch {
            runCatching {
                val res = RetrofitClient.getInstance(requireContext()).getBarang()
                if (res.isSuccessful && res.body()?.success == true) barangList = res.body()?.data?.items ?: emptyList()
            }
            val dipilih = barangList.find { it.id == barangId }
            if (dipilih != null) {
                selectBrg.updateSelectText("${dipilih.kode_sku} - ${dipilih.nama}")
            }
        }

        selectBrg.setOnClickListener {
            if (barangList.isEmpty()) {
                Toast.makeText(requireContext(), "Data barang belum tersedia", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            val labels = barangList.map { "${it.kode_sku} - ${it.nama}" }.toTypedArray()
            showPicker("Pilih Barang", labels, selectBrg) { barangId = barangList[it].id }
        }

        selectTgl.setOnClickListener {
            val cal = Calendar.getInstance()
            DatePickerDialog(requireContext(), { _, y, m, d ->
                tanggal = String.format(Locale.US, "%04d-%02d-%02d", y, m + 1, d)
                selectTgl.updateSelectText(tanggal)
            }, cal.get(Calendar.YEAR), cal.get(Calendar.MONTH), cal.get(Calendar.DAY_OF_MONTH)).show()
        }

        view.findViewById<View>(R.id.btn_pilih_foto).setOnClickListener { pickImage.launch("image/*") }

        view.findViewById<View>(R.id.btn_simpan).setOnClickListener {
            val nomor = etBatch.text.toString().trim()
            val fieldBelumDiisi = mutableListOf<String>()
            if (barangId <= 0) {
                fieldBelumDiisi.add("barang")
            }
            if (nomor.isEmpty()) {
                fieldBelumDiisi.add("nomor batch")
            }
            if (tanggal.isEmpty()) {
                fieldBelumDiisi.add("tanggal kedaluwarsa")
            }
            if (fieldBelumDiisi.isNotEmpty()) {
                Toast.makeText(
                    requireContext(),
                    "Lengkapi ${fieldBelumDiisi.joinToString(", ")} terlebih dahulu",
                    Toast.LENGTH_SHORT,
                ).show()
                return@setOnClickListener
            }

            val tombolSimpan = view.findViewById<View>(R.id.btn_simpan)
            viewLifecycleOwner.lifecycleScope.launch {
                tombolSimpan.isEnabled = false
                tombolSimpan.alpha = 0.6f
                var batchTersimpan = false
                try {
                    val api = RetrofitClient.getInstance(requireContext())
                    val response = api.storeBatchStok(
                        barangId,
                        BatchStokRequest(nomor, jumlah, tanggal),
                    )
                    val body = response.body()
                    if (!response.isSuccessful || body?.success != true) {
                        Toast.makeText(
                            requireContext(),
                            uraikanGagal(response, "Gagal menyimpan batch"),
                            Toast.LENGTH_LONG,
                        ).show()
                        return@launch
                    }

                    batchTersimpan = true
                    val uri = imageUri
                    if (uri == null) {
                        Toast.makeText(
                            requireContext(),
                            "Batch stok berhasil ditambahkan",
                            Toast.LENGTH_SHORT,
                        ).show()
                        findNavController().navigateUp()
                        return@launch
                    }

                    val batchId = body?.data?.id
                    if (batchId == null) {
                        Toast.makeText(
                            requireContext(),
                            "Batch tersimpan, tetapi ID batch tidak diterima sehingga foto belum diunggah",
                            Toast.LENGTH_LONG,
                        ).show()
                        findNavController().navigateUp()
                        return@launch
                    }

                    val contentResolver = requireContext().contentResolver
                    val mimeType = contentResolver.getType(uri)
                    val extension = when (mimeType) {
                        "image/jpeg" -> "jpg"
                        "image/png" -> "png"
                        else -> throw IOException("Foto harus berformat JPG atau PNG")
                    }
                    val file = File(requireContext().cacheDir, "bukti_batch_$batchId.$extension")
                    val uploadResponse = try {
                        val input = contentResolver.openInputStream(uri)
                            ?: throw IOException("Foto tidak dapat dibaca")
                        input.use { stream ->
                            file.outputStream().use { output -> stream.copyTo(output) }
                        }
                        val filePart = MultipartBody.Part.createFormData(
                            "lampiran",
                            file.name,
                            file.asRequestBody(mimeType.toMediaTypeOrNull()),
                        )
                        val jenisPart = MultipartBody.Part.createFormData("jenis", "bukti_batch")
                        api.uploadLampiran(batchId, filePart, jenisPart)
                    } finally {
                        file.delete()
                    }

                    if (uploadResponse.isSuccessful && uploadResponse.body()?.success == true) {
                        view.findViewById<TextView>(R.id.tv_status_foto_batch).text = "Foto batch berhasil diunggah"
                        Toast.makeText(
                            requireContext(),
                            "Batch dan foto batch berhasil disimpan",
                            Toast.LENGTH_SHORT,
                        ).show()
                    } else {
                        view.findViewById<TextView>(R.id.tv_status_foto_batch).text = "Foto gagal diunggah"
                        Toast.makeText(
                            requireContext(),
                            "Batch tersimpan, tetapi foto gagal diunggah: ${uraikanGagal(uploadResponse, "unggahan ditolak")}",
                            Toast.LENGTH_LONG,
                        ).show()
                    }
                    findNavController().navigateUp()
                } catch (exception: Exception) {
                    val message = if (batchTersimpan) {
                        "Batch tersimpan, tetapi foto gagal diunggah: ${exception.message ?: "kesalahan berkas"}"
                    } else {
                        "Gagal menyimpan batch: ${exception.message ?: "kesalahan jaringan"}"
                    }
                    Toast.makeText(requireContext(), message, Toast.LENGTH_LONG).show()
                    if (batchTersimpan) {
                        findNavController().navigateUp()
                    }
                } finally {
                    tombolSimpan.isEnabled = true
                    tombolSimpan.alpha = 1f
                }
            }
        }
    }
}

class FormStokKeluarFragment : Fragment(R.layout.form_stok_keluar) {

    private var barangList: List<BarangItem> = emptyList()
    private var barangId = -1
    private var jumlah = 1

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        bindCloseButtons(view)

        val selectBrg = view.findViewById<View>(R.id.select_barang)
        val etJumlah = view.findViewById<EditText>(R.id.keluar_jumlah)
        val etCatatan = view.findViewById<EditText>(R.id.et_catatan)


        arguments?.getInt("barangId")?.takeIf { it != -1 }?.let { barangId = it }

        etJumlah?.setText(jumlah.toString())
        etJumlah?.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                jumlah = s?.toString()?.trim()?.toIntOrNull() ?: 0
            }
        })

        view.findViewById<View>(R.id.btn_keluar_minus).setOnClickListener {
            if (jumlah > 1) {
                jumlah--
                etJumlah?.setText(jumlah.toString())
                etJumlah?.setSelection(etJumlah.text.length)
            }
        }
        view.findViewById<View>(R.id.btn_keluar_plus).setOnClickListener {
            jumlah++
            etJumlah?.setText(jumlah.toString())
            etJumlah?.setSelection(etJumlah.text.length)
        }

        viewLifecycleOwner.lifecycleScope.launch {
            runCatching {
                val res = RetrofitClient.getInstance(requireContext()).getBarang()
                if (res.isSuccessful && res.body()?.success == true) barangList = res.body()?.data?.items ?: emptyList()
            }
            val dipilih = barangList.find { it.id == barangId }
            if (dipilih != null) {
                selectBrg.updateSelectText("${dipilih.nama} (Stok: ${dipilih.total_stok_tersedia} ${dipilih.satuan})")
            }
        }

        selectBrg.setOnClickListener {
            if (barangList.isEmpty()) {
                Toast.makeText(requireContext(), "Data barang belum tersedia", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            val labels = barangList.map { "${it.nama} (Stok: ${it.total_stok_tersedia} ${it.satuan})" }.toTypedArray()
            showPicker("Pilih Barang Keluar (FEFO)", labels, selectBrg) { i ->
                barangId = barangList[i].id
            }
        }

        view.findViewById<View>(R.id.btn_catat_keluar).setOnClickListener {
            if (barangId == -1) {
                Toast.makeText(requireContext(), "Pilih barang terlebih dahulu", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            val inputJumlah = etJumlah?.text?.toString()?.trim()?.toIntOrNull() ?: 0
            if (inputJumlah <= 0) {
                Toast.makeText(requireContext(), "Jumlah keluar minimal 1", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            val dipilih = barangList.find { it.id == barangId }
            val stokTersedia = dipilih?.total_stok_tersedia ?: Int.MAX_VALUE
            if (inputJumlah > stokTersedia) {
                Toast.makeText(
                    requireContext(),
                    "Peringatan: Jumlah keluar ($inputJumlah) melebihi stok tersedia (${stokTersedia} ${dipilih?.satuan ?: "unit"})!",
                    Toast.LENGTH_LONG
                ).show()
                return@setOnClickListener
            }
            jumlah = inputJumlah
            val catatan = etCatatan.text.toString().trim().ifEmpty { null }
            submitApi(
                "Stok keluar FEFO berhasil dicatat",
                "Gagal mencatat stok keluar",
                { it.storeStokKeluar(TransaksiRequest(jenis = "keluar", barang_id = barangId, jumlah = jumlah, catatan = catatan)) },
                tombol = view.findViewById(R.id.btn_catat_keluar),
            )
        }
    }
}

class FormPenyesuaianFragment : Fragment(R.layout.form_penyesuaian) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        bindCloseButtons(view)

        val batchId = arguments?.getInt("batchId") ?: -1
        val barangId = arguments?.getInt("barangId") ?: -1
        val stokAwal = arguments?.getInt("jumlahSisa") ?: 0
        val etSelisih = view.findViewById<EditText>(R.id.adjust_selisih)
        val tvPreview = view.findViewById<TextView>(R.id.adjust_preview)
        val etAlasan = view.findViewById<EditText>(R.id.et_alasan)

        etSelisih.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                tvPreview.text = (stokAwal + (s?.toString()?.toIntOrNull() ?: 0)).toString()
            }
        })

        view.findViewById<View>(R.id.btn_simpan).setOnClickListener {
            val selisih = etSelisih.text.toString().trim().toIntOrNull()
            val alasan = etAlasan.text.toString().trim()
            if (selisih == null || selisih == 0 || alasan.isEmpty()) {
                Toast.makeText(requireContext(), "Selisih dan alasan penyesuaian wajib diisi", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            submitApi(
                "Penyesuaian stok berhasil disimpan",
                "Gagal menyimpan penyesuaian",
                { api ->


                    val bId = if (barangId != -1) barangId
                    else api.getDetailBatch(batchId).body()?.data?.barang_id ?: -1
                    if (bId == -1) throw IllegalStateException("Barang tidak dikenali untuk penyesuaian")
                    api.storePenyesuaian(
                        TransaksiRequest(jenis = "penyesuaian", barang_id = bId, jumlah = selisih, catatan = alasan, batch_id = batchId),
                    )
                },
                tombol = view.findViewById(R.id.btn_simpan),
            )
        }
    }
}

class FormAkunFragment : Fragment(R.layout.form_akun) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        bindCloseButtons(view)

        val etNama = view.findViewById<EditText>(R.id.et_nama)
        val etUser = view.findViewById<EditText>(R.id.et_username)
        val etPass = view.findViewById<EditText>(R.id.et_password)
        val switchAktif = view.findViewById<CompoundButton>(R.id.switch_aktif)

        view.findViewById<View>(R.id.btn_simpan).setOnClickListener {
            val nama = etNama.text.toString().trim()
            val username = etUser.text.toString().trim()
            val password = etPass.text.toString().trim()
            if (nama.isEmpty() || username.isEmpty() || password.isEmpty()) {
                Toast.makeText(requireContext(), "Nama, username, dan password wajib diisi", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            submitApi(
                "Akun pengguna berhasil disimpan",
                "Gagal menyimpan akun",
                {

                    it.storeUser(UserRequest(nama, username, password))
                },
                tombol = view.findViewById(R.id.btn_simpan),
            )
        }
    }
}