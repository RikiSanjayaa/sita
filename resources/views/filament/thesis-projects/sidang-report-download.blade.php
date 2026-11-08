<div
    x-data="{ busy: false, error: '', status: '' }"
    x-init="$store.sidangReport = { busy: false }"
    x-on:sidang-report-download.window="
        if (!busy) {
            busy = true;
            $store.sidangReport.busy = true;
            error = '';
            status = 'Menyiapkan file Excel...';
            try {
                const response = await fetch($event.detail.url, { headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    const result = await response.json().catch(() => ({}));
                    throw new Error(response.status === 422 && result.message
                        ? result.message : 'Excel belum bisa diunduh. Coba lagi.');
                }
                if (!response.headers.get('content-disposition')?.includes('attachment')) {
                    throw new Error('Excel belum bisa diunduh. Muat ulang halaman jika sesi sudah berakhir.');
                }
                const url = URL.createObjectURL(await response.blob());
                const link = document.createElement('a');
                link.href = url;
                link.download = 'rekap-nilai-sidang.xlsx';
                link.click();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
                status = 'Unduhan Excel dimulai.';
            } catch (failure) {
                error = failure instanceof TypeError ? 'Excel belum bisa diunduh. Coba lagi.' : failure.message;
                status = '';
            } finally {
                busy = false;
                $store.sidangReport.busy = false;
            }
        }
    "
>
    <p x-cloak x-show="error" x-text="error" role="alert" class="text-sm"></p>
    <p x-cloak x-show="status" x-text="status" role="status" class="text-sm"></p>
</div>
