<?php
/**
 * Inline import widget. Set before include:
 * $importType = 'orders'|'tickets'
 * $importLabel = 'Orders'|'Support Tickets'
 * $sampleType = 'orders'|'tickets'
 */
$importType = $importType ?? 'orders';
$importLabel = $importLabel ?? ucfirst($importType);
$sampleType = $sampleType ?? $importType;
$wid = preg_replace('/[^a-z]/', '', $importType);
?>
<div class="import-panel" id="import-section">
    <div class="import-panel-header">
        <h3><i class="fas fa-file-import"></i> Import <?= htmlspecialchars($importLabel) ?></h3>
        <a href="download_sample.php?type=<?= urlencode($sampleType) ?>" class="btn-import-outline">
            <i class="fas fa-download"></i> Sample CSV
        </a>
    </div>
    <div id="resultAlert-<?= $wid ?>" class="import-alert" style="display:none;"></div>
    <form id="importForm-<?= $wid ?>" class="import-form" enctype="multipart/form-data">
        <input type="hidden" name="import_type" value="<?= htmlspecialchars($importType) ?>">
        <input type="file" name="data_file" id="dataFile-<?= $wid ?>" accept=".csv,.xlsx,.xls" required>
        <button type="submit" class="btn-import" id="importBtn-<?= $wid ?>">
            <i class="fas fa-upload"></i> Import
        </button>
    </form>
    <p class="import-hint">Supports .csv, .xlsx, .xls</p>
</div>

<div class="modal-overlay import-modal" id="progressModal-<?= $wid ?>">
    <div class="modal-box" id="modalBox-<?= $wid ?>">
        <div class="modal-icon" id="modalIcon-<?= $wid ?>"><i class="fas fa-cloud-upload-alt spinner"></i></div>
        <h3 id="modalTitle-<?= $wid ?>">Importing Data</h3>
        <p class="modal-status" id="modalStatus-<?= $wid ?>">Preparing upload...</p>
        <div class="progress-track"><div class="progress-fill" id="progressFill-<?= $wid ?>"></div></div>
        <div class="progress-pct" id="progressPct-<?= $wid ?>">0%</div>
        <div class="modal-steps">
            <div class="step-item" data-step="1"><i class="fas fa-circle-notch"></i> Uploading file</div>
            <div class="step-item" data-step="2"><i class="far fa-circle"></i> Parsing data</div>
            <div class="step-item" data-step="3"><i class="far fa-circle"></i> Importing records</div>
            <div class="step-item" data-step="4"><i class="far fa-circle"></i> Finalizing</div>
        </div>
        <button type="button" class="modal-close" id="modalClose-<?= $wid ?>">Done</button>
    </div>
</div>

<script>
(function() {
    const wid = <?= json_encode($wid) ?>;
    const form = document.getElementById('importForm-' + wid);
    const modal = document.getElementById('progressModal-' + wid);
    const modalBox = document.getElementById('modalBox-' + wid);
    const modalIcon = document.getElementById('modalIcon-' + wid);
    const modalTitle = document.getElementById('modalTitle-' + wid);
    const modalStatus = document.getElementById('modalStatus-' + wid);
    const progressFill = document.getElementById('progressFill-' + wid);
    const progressPct = document.getElementById('progressPct-' + wid);
    const modalClose = document.getElementById('modalClose-' + wid);
    const importBtn = document.getElementById('importBtn-' + wid);
    const resultAlert = document.getElementById('resultAlert-' + wid);
    const steps = modal.querySelectorAll('.step-item');
    let processTimer = null;
    let importSucceeded = false;

    function setProgress(pct, status) {
        progressFill.style.width = pct + '%';
        progressPct.textContent = Math.round(pct) + '%';
        if (status) modalStatus.textContent = status;
    }

    function setStep(activeIndex) {
        steps.forEach((step, i) => {
            step.classList.remove('active', 'done');
            const icon = step.querySelector('i');
            if (i < activeIndex) { step.classList.add('done'); icon.className = 'fas fa-check-circle'; }
            else if (i === activeIndex) { step.classList.add('active'); icon.className = 'fas fa-circle-notch spinner'; }
            else { icon.className = 'far fa-circle'; }
        });
    }

    function showModal() {
        importSucceeded = false;
        modalBox.classList.remove('success', 'error');
        modalIcon.innerHTML = '<i class="fas fa-cloud-upload-alt spinner"></i>';
        modalTitle.textContent = 'Importing Data';
        modalClose.classList.remove('show');
        setProgress(0, 'Preparing upload...');
        setStep(0);
        modal.classList.add('active');
    }

    function showSuccess(message) {
        clearInterval(processTimer);
        importSucceeded = true;
        modalBox.classList.add('success');
        modalIcon.innerHTML = '<i class="fas fa-check"></i>';
        modalTitle.textContent = 'Import Complete!';
        modalStatus.textContent = message;
        setProgress(100);
        setStep(4);
        steps.forEach(s => { s.classList.add('done'); s.querySelector('i').className = 'fas fa-check-circle'; });
        modalClose.classList.add('show');
        resultAlert.className = 'import-alert import-alert-success';
        resultAlert.textContent = message;
        resultAlert.style.display = 'block';
    }

    function showError(message) {
        clearInterval(processTimer);
        modalBox.classList.add('error');
        modalIcon.innerHTML = '<i class="fas fa-times"></i>';
        modalTitle.textContent = 'Import Failed';
        modalStatus.textContent = message;
        modalClose.classList.add('show');
        resultAlert.className = 'import-alert import-alert-error';
        resultAlert.textContent = message;
        resultAlert.style.display = 'block';
    }

    function simulateProcessing(fromPct) {
        let pct = fromPct;
        setStep(2);
        modalStatus.textContent = 'Parsing file data...';
        processTimer = setInterval(() => {
            if (pct < 92) {
                pct += Math.random() * 4;
                setProgress(Math.min(pct, 92));
                if (pct > 55) { setStep(3); modalStatus.textContent = 'Importing records into database...'; }
            }
        }, 300);
    }

    modalClose.addEventListener('click', () => {
        modal.classList.remove('active');
        importBtn.disabled = false;
        if (importSucceeded) window.location.reload();
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!document.getElementById('dataFile-' + wid).files.length) return;

        showModal();
        importBtn.disabled = true;
        resultAlert.style.display = 'none';

        const xhr = new XMLHttpRequest();
        const formData = new FormData(form);

        xhr.upload.addEventListener('progress', (ev) => {
            if (ev.lengthComputable) {
                setProgress((ev.loaded / ev.total) * 45, 'Uploading file...');
                setStep(0);
            }
        });
        xhr.upload.addEventListener('load', () => {
            setProgress(48, 'Upload complete. Processing...');
            setStep(1);
            steps[0].classList.add('done');
            steps[0].querySelector('i').className = 'fas fa-check-circle';
            simulateProcessing(50);
        });
        xhr.addEventListener('load', () => {
            clearInterval(processTimer);
            handleUploadComplete(xhr);
        });
        xhr.addEventListener('error', () => {
            showError('Network error. Check your connection and try again.');
            importBtn.disabled = false;
        });
        xhr.open('POST', 'import_api.php');
        xhr.send(formData);
    });

    async function parseJsonResponse(xhr) {
        const text = (xhr.responseText || '').trim();
        if (!text) {
            throw new Error('Server returned an empty response. The import may have timed out — try again or use a smaller file.');
        }
        try {
            return JSON.parse(text);
        } catch {
            if (xhr.status === 401) {
                throw new Error('Session expired. Please log in again.');
            }
            if (xhr.status >= 500) {
                throw new Error('Server error during import. Please try again.');
            }
            throw new Error('Unexpected server response. Please try again.');
        }
    }

    async function runImportChunks(jobId, total) {
        setStep(3);
        let done = false;
        while (!done) {
            const chunkData = new FormData();
            chunkData.append('action', 'chunk');
            chunkData.append('job_id', jobId);

            const resp = await fetch('import_api.php', { method: 'POST', body: chunkData, credentials: 'same-origin' });
            const text = await resp.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                throw new Error('Import interrupted. Please try again.');
            }
            if (!data.success && data.phase !== 'done') {
                throw new Error(data.message || 'Import failed during processing.');
            }

            const offset = data.offset ?? total;
            const pct = 50 + Math.min(45, (offset / Math.max(total, 1)) * 45);
            setProgress(pct, data.message || ('Imported ' + offset.toLocaleString() + ' of ' + total.toLocaleString() + ' rows…'));

            if (data.done) {
                return data;
            }
        }
        throw new Error('Import did not complete.');
    }

    async function handleUploadComplete(xhr) {
        try {
            const startData = await parseJsonResponse(xhr);
            if (!startData.success) {
                showError(startData.message || 'Import failed.');
                importBtn.disabled = false;
                return;
            }

            setProgress(50, startData.message || 'File parsed. Importing records…');
            setStep(2);
            steps[1].classList.add('done');
            steps[1].querySelector('i').className = 'fas fa-check-circle';

            const finalData = await runImportChunks(startData.job_id, startData.total || 0);
            setProgress(100, 'Finalizing import…');
            setStep(4);
            steps.forEach(s => { s.classList.add('done'); s.querySelector('i').className = 'fas fa-check-circle'; });

            if (finalData.success) {
                showSuccess(finalData.message);
            } else {
                showError(finalData.message || 'Import finished with issues.');
            }
        } catch (err) {
            showError(err.message || 'Import failed.');
        }
        importBtn.disabled = false;
    }
})();
</script>
