{{--
    Cascading Kota → Kecamatan → Desa/Kelurahan untuk form Data Diri di
    Laporan Survey & Laporan Pemasangan. Satu sumber supaya dua halaman tidak
    menyalin helper yang sama.

    Kontrak DOM (id tetap, satu form per halaman):
      #identity-city_id      <select> kota  — data-selected-district & data-selected-village
                             berisi nilai awal (dari data pelanggan / old()).
      #identity-district_id  <select> kecamatan — diisi lewat JS.
      #identity-village_id   <select> desa — diisi lewat JS.

    Endpoint sama dengan Edit Verifikasi & Edit Pelanggan:
    /api/cities/{city}/districts, /api/districts/{district}/villages.

    Di-include sekali per halaman di section scripts. Prefill dijalankan sendiri
    saat DOMContentLoaded, jadi halaman tidak perlu memanggil apa pun.
--}}
<script>
    async function wilayahLoadDistricts(cityId, selectedDistrictId = null, selectedVillageId = null) {
        const districtSelect = document.getElementById('identity-district_id');
        const villageSelect = document.getElementById('identity-village_id');
        if (!districtSelect || !villageSelect) return;

        districtSelect.innerHTML = '<option value="">Memuat...</option>';
        villageSelect.innerHTML = '<option value="">Pilih kecamatan dulu</option>';
        if (!cityId) {
            districtSelect.innerHTML = '<option value="">Pilih kota/kabupaten dulu</option>';
            return;
        }

        try {
            const res = await fetch(`/api/cities/${cityId}/districts`);
            const districts = await res.json();
            districtSelect.innerHTML = '<option value="">Pilih Kecamatan</option>' +
                districts.map(d => `<option value="${d.id}" ${String(d.id) === String(selectedDistrictId) ? 'selected' : ''}>${d.name}</option>`).join('');
            if (selectedDistrictId) {
                wilayahLoadVillages(selectedDistrictId, selectedVillageId);
            }
        } catch (err) {
            districtSelect.innerHTML = '<option value="">Gagal memuat kecamatan</option>';
        }
    }

    async function wilayahLoadVillages(districtId, selectedVillageId = null) {
        const villageSelect = document.getElementById('identity-village_id');
        if (!villageSelect) return;

        villageSelect.innerHTML = '<option value="">Memuat...</option>';
        if (!districtId) {
            villageSelect.innerHTML = '<option value="">Pilih kecamatan dulu</option>';
            return;
        }

        try {
            const res = await fetch(`/api/districts/${districtId}/villages`);
            const villages = await res.json();
            villageSelect.innerHTML = '<option value="">Pilih Desa/Kelurahan</option>' +
                villages.map(v => `<option value="${v.id}" ${String(v.id) === String(selectedVillageId) ? 'selected' : ''}>${v.name}</option>`).join('');
        } catch (err) {
            villageSelect.innerHTML = '<option value="">Gagal memuat desa</option>';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const citySelect = document.getElementById('identity-city_id');
        if (citySelect && citySelect.value) {
            wilayahLoadDistricts(
                citySelect.value,
                citySelect.dataset.selectedDistrict || null,
                citySelect.dataset.selectedVillage || null
            );
        }
    });
</script>
