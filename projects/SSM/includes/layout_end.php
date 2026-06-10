        </div>
    </div>
</div>

<?php if ($showMagicFab): ?>
<button type="button" class="magic-fab" id="magicFab" title="AI Assistant"><i class="fas fa-magic"></i></button>
<div class="ai-panel" id="aiPanel">
    <div style="display:flex; gap:12px; align-items:center; margin-bottom:12px;">
        <i class="fas fa-magic" style="color:#FC8019;"></i>
        <strong>AI Resolution Assistant</strong>
    </div>
    <div style="font-size:14px; color:#475569; line-height:1.6;">
        <p style="margin-bottom:8px;">💡 Suggest: Offer ₹150 coupon for late delivery</p>
        <p style="margin-bottom:8px;">📋 Similar resolved: #T302, #T445</p>
        <p>😟 Sentiment: Urgent → escalate to senior agent</p>
    </div>
    <button type="button" style="background:#FC8019; color:white; border:none; padding:10px; width:100%; border-radius:40px; margin-top:14px; cursor:pointer; font-weight:600;">Apply Suggestion</button>
</div>
<script>
document.getElementById('magicFab')?.addEventListener('click', () => {
    document.getElementById('aiPanel')?.classList.toggle('open');
});
</script>
<?php endif; ?>

<div class="reset-modal modal-overlay" id="resetDataModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Reset all data?</h3>
        <p>This will permanently delete <strong>all orders</strong> and <strong>support tickets</strong>. Admin accounts are kept. This cannot be undone.</p>
        <form method="POST" action="reset_data.php" class="reset-modal-actions" id="resetDataForm">
            <button type="button" class="btn-reset-cancel" id="resetDataCancel">Cancel</button>
            <button type="submit" class="btn-reset-confirm" id="resetDataConfirm">Yes, delete everything</button>
        </form>
    </div>
</div>
<script>
(function() {
    const btn = document.getElementById('resetDataBtn');
    const modal = document.getElementById('resetDataModal');
    const cancel = document.getElementById('resetDataCancel');
    const form = document.getElementById('resetDataForm');
    const confirmBtn = document.getElementById('resetDataConfirm');
    if (!btn || !modal) return;

    btn.addEventListener('click', () => modal.classList.add('active'));
    cancel.addEventListener('click', () => modal.classList.remove('active'));
    modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('active'); });
    form.addEventListener('submit', () => {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Deleting…';
    });
})();
</script>
</body>
</html>
