package com.example.stockflow

import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.navigation.fragment.NavHostFragment
import com.google.android.material.bottomnavigation.BottomNavigationView

class MainActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        val role = intent.getStringExtra("USER_ROLE") ?: "pemilik"
        val isOwner = role == "pemilik"

        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as NavHostFragment
        val navController = navHostFragment.navController

        val startDestId = if (isOwner) R.id.nav_beranda_owner else R.id.nav_beranda_staff
        val navGraph = navController.navInflater.inflate(R.navigation.nav_main)
        navGraph.setStartDestination(startDestId)
        navController.graph = navGraph

        val bottomNav = findViewById<BottomNavigationView>(R.id.bottom_nav)
        val transaksiItem = bottomNav.menu.findItem(R.id.nav_transaksi)
        if (!isOwner) {
            transaksiItem.title = "Riwayat"
            transaksiItem.setIcon(R.drawable.ic_history)
        }

        bottomNav.setOnItemSelectedListener { item ->
            val targetDestId = when (item.itemId) {
                R.id.nav_beranda -> if (isOwner) R.id.nav_beranda_owner else R.id.nav_beranda_staff
                R.id.nav_barang -> if (isOwner) R.id.nav_barang_owner else R.id.nav_barang_staff
                R.id.nav_transaksi -> if (isOwner) R.id.nav_laporan else R.id.nav_riwayat
                R.id.nav_profil -> R.id.nav_profil
                else -> return@setOnItemSelectedListener false
            }

            runCatching {
                navController.navigate(targetDestId)
            }
            true
        }

        val topLevelDestinations = setOf(
            R.id.nav_beranda_owner,
            R.id.nav_beranda_staff,
            R.id.nav_barang_owner,
            R.id.nav_barang_staff,
            R.id.nav_laporan,
            R.id.nav_riwayat,
            R.id.nav_profil,
            R.id.perluDitanganiFragment
        )

        navController.addOnDestinationChangedListener { _, destination, _ ->
            bottomNav.visibility = if (destination.id in topLevelDestinations) {
                View.VISIBLE
            } else {
                View.GONE
            }

            val menuId = when (destination.id) {
                R.id.nav_beranda_owner, R.id.nav_beranda_staff, R.id.perluDitanganiFragment -> R.id.nav_beranda
                R.id.nav_barang_owner, R.id.nav_barang_staff -> R.id.nav_barang
                R.id.nav_laporan, R.id.nav_riwayat -> R.id.nav_transaksi
                R.id.nav_profil -> R.id.nav_profil
                else -> null
            }
            menuId?.let { bottomNav.menu.findItem(it)?.isChecked = true }
        }
    }
}