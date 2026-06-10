<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/rocky_simulator.php';
requireLogin();

$admin = currentAdmin();
$activePage = 'rocky';
$pageTitle = 'Rocky.AI';
$pageSubtitle = 'AI chatbot training simulation (demo data)';

$search = trim($_GET['q'] ?? '');
$selectedId = trim($_GET['ticket'] ?? '');

$allTickets = getRockyDummyTickets();
$tickets = getRockyTicketsForSidebar($search);
$totalTickets = count($allTickets);

$selectedTicket = $selectedId !== '' ? getRockyTicketById($selectedId) : null;
if (!$selectedTicket && !empty($tickets)) {
    $selectedTicket = $tickets[0];
    $selectedId = $selectedTicket['ticket_id'];
}
$chatMessages = $selectedTicket ? generateRockyChat($selectedTicket) : [];

include __DIR__ . '/includes/layout_start.php';
?>

<style>
    .rocky-layout {
        display: grid; grid-template-columns: 320px 1fr; gap: 0;
        background: white; border-radius: 28px; border: 1px solid #EDF2F7;
        box-shadow: 0 4px 12px rgba(0,0,0,.03); min-height: calc(100vh - 180px);
        overflow: hidden;
    }
    .rocky-sidebar {
        border-right: 1px solid #EDF2F7; display: flex; flex-direction: column;
        background: #FAFBFC; min-height: 0;
    }
    .rocky-sidebar-head {
        padding: 18px 16px 12px; border-bottom: 1px solid #EDF2F7;
        background: linear-gradient(180deg, #FFF7ED 0%, #FAFBFC 100%);
    }
    .rocky-sidebar-head h2 {
        font-size: 15px; color: #1E2A3E; margin-bottom: 4px;
        display: flex; align-items: center; gap: 8px;
    }
    .rocky-sidebar-head h2 i { color: #FC8019; }
    .rocky-sidebar-head p { font-size: 12px; color: #64748B; margin-bottom: 12px; }
    .rocky-demo-badge {
        display: inline-block; font-size: 10px; font-weight: 700; padding: 3px 10px;
        border-radius: 999px; background: #EFF6FF; color: #0284C7; margin-bottom: 10px;
    }
    .rocky-search {
        display: flex; align-items: center; gap: 8px; background: white;
        border: 1px solid #E2E8F0; border-radius: 12px; padding: 8px 12px;
    }
    .rocky-search input {
        border: none; outline: none; width: 100%; font-size: 13px; background: transparent;
    }
    .rocky-ticket-list { overflow-y: auto; flex: 1; padding: 8px; }
    .rocky-ticket-item {
        display: block; width: 100%; text-align: left; border: 1px solid transparent;
        background: white; border-radius: 14px; padding: 12px 14px; margin-bottom: 6px;
        cursor: pointer; transition: border-color .15s, box-shadow .15s;
    }
    .rocky-ticket-item:hover { border-color: #FED7AA; box-shadow: 0 2px 8px rgba(252,128,25,.08); }
    .rocky-ticket-item.active {
        border-color: #FC8019; background: #FFFBF5;
        box-shadow: 0 2px 10px rgba(252,128,25,.12);
    }
    .rocky-ticket-item strong { font-size: 13px; color: #1E2A3E; display: block; }
    .rocky-ticket-item span { font-size: 11px; color: #64748B; display: block; margin-top: 3px; }
    .rocky-ticket-item .cat {
        display: inline-block; margin-top: 6px; font-size: 10px; font-weight: 700;
        padding: 2px 8px; border-radius: 999px; background: #EFF6FF; color: #0284C7;
    }
    .rocky-sidebar-foot {
        padding: 10px 14px; font-size: 11px; color: #94A3B8; border-top: 1px solid #EDF2F7;
    }
    .rocky-main { display: flex; flex-direction: column; min-height: 0; min-width: 0; }
    .rocky-chat-head {
        padding: 16px 22px; border-bottom: 1px solid #EDF2F7;
        display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
    }
    .rocky-chat-head h3 { font-size: 16px; color: #1E2A3E; margin: 0; }
    .rocky-chat-head p { font-size: 12px; color: #64748B; margin-top: 4px; }
    .rocky-sim-badge {
        font-size: 11px; font-weight: 700; padding: 6px 12px; border-radius: 999px;
        background: #F0FDF4; color: #15803D; border: 1px solid #BBF7D0;
    }
    .rocky-chat-body {
        flex: 1; overflow-y: auto; padding: 20px 22px; background: #F8FAFC;
        display: flex; flex-direction: column; gap: 14px;
    }
    .rocky-bubble {
        max-width: 78%; padding: 12px 16px; border-radius: 18px; font-size: 14px; line-height: 1.55;
    }
    .rocky-bubble--customer {
        align-self: flex-start; background: white; border: 1px solid #E2E8F0;
        border-bottom-left-radius: 6px;
    }
    .rocky-bubble--agent {
        align-self: flex-end; background: linear-gradient(135deg, #FFF7ED, #FFEDD5);
        border: 1px solid #FED7AA; border-bottom-right-radius: 6px;
    }
    .rocky-bubble-meta {
        display: flex; justify-content: space-between; gap: 12px;
        font-size: 11px; margin-bottom: 6px; color: #64748B;
    }
    .rocky-bubble-meta strong { color: #334155; }
    .rocky-bubble p { margin: 0; color: #1E293B; }
    .rocky-label-bar {
        padding: 16px 22px; border-top: 1px solid #EDF2F7; background: white;
        display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
    }
    .rocky-label-bar p { font-size: 13px; color: #475569; margin: 0; }
    .rocky-label-bar p strong { color: #1E2A3E; }
    .rocky-label-btns { display: flex; gap: 10px; flex-wrap: wrap; }
    .rocky-label-btn {
        border: 2px solid #E2E8F0; background: white; padding: 10px 18px;
        border-radius: 999px; font-size: 13px; font-weight: 600; cursor: pointer;
        display: flex; align-items: center; gap: 8px; transition: all .15s;
    }
    .rocky-label-btn:hover { transform: translateY(-1px); }
    .rocky-label-btn--good { color: #15803D; }
    .rocky-label-btn--good:hover, .rocky-label-btn--good.selected {
        border-color: #86EFAC; background: #F0FDF4;
    }
    .rocky-label-btn--bad { color: #B91C1C; }
    .rocky-label-btn--bad:hover, .rocky-label-btn--bad.selected {
        border-color: #FCA5A5; background: #FEF2F2;
    }
    .rocky-label-btn--improve { color: #B45309; }
    .rocky-label-btn--improve:hover, .rocky-label-btn--improve.selected {
        border-color: #FCD34D; background: #FFFBEB;
    }
    .rocky-empty {
        flex: 1; display: flex; flex-direction: column; align-items: center;
        justify-content: center; color: #94A3B8; padding: 40px; text-align: center;
    }
    .rocky-empty i { font-size: 48px; color: #FC8019; margin-bottom: 16px; opacity: .6; }
    .rocky-toast {
        position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(80px);
        background: #1E293B; color: white; padding: 12px 22px; border-radius: 999px;
        font-size: 13px; font-weight: 600; z-index: 999; opacity: 0;
        transition: transform .3s, opacity .3s; pointer-events: none;
    }
    .rocky-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
    @media (max-width: 900px) {
        .rocky-layout { grid-template-columns: 1fr; min-height: auto; }
        .rocky-sidebar { max-height: 280px; border-right: none; border-bottom: 1px solid #EDF2F7; }
    }
</style>

<?php if (empty($tickets)): ?>
<div class="panel empty-state">
    <i class="fas fa-robot fa-3x" style="color:#FC8019; margin-bottom:16px;"></i>
    <h3>No matching demo tickets</h3>
    <p><a href="rocky.php">Clear search</a> to see all 10 sample conversations.</p>
</div>
<?php else: ?>

<div class="rocky-layout">
    <aside class="rocky-sidebar">
        <div class="rocky-sidebar-head">
            <h2><i class="fas fa-robot"></i> Rocky.AI</h2>
            <span class="rocky-demo-badge"><i class="fas fa-flask"></i> 10 demo tickets</span>
            <p>Select a ticket to review the simulated conversation</p>
            <form method="GET" action="rocky.php" class="rocky-search">
                <?php if ($selectedId): ?><input type="hidden" name="ticket" value="<?= htmlspecialchars($selectedId) ?>"><?php endif; ?>
                <i class="fas fa-search" style="color:#94A3B8;font-size:13px;"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search demo tickets…">
            </form>
        </div>
        <div class="rocky-ticket-list" id="rockyTicketList">
            <?php foreach ($tickets as $t): ?>
            <button type="button"
                class="rocky-ticket-item<?= ($selectedId === $t['ticket_id']) ? ' active' : '' ?>"
                data-ticket-id="<?= htmlspecialchars($t['ticket_id']) ?>">
                <strong><?= htmlspecialchars($t['ticket_id']) ?></strong>
                <span><?= htmlspecialchars($t['customer_name']) ?> · <?= htmlspecialchars($t['time_label']) ?></span>
                <span class="cat"><?= htmlspecialchars($t['category']) ?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="rocky-sidebar-foot">
            <?= count($tickets) ?> of <?= $totalTickets ?> demo tickets shown
        </div>
    </aside>

    <section class="rocky-main" id="rockyMain">
        <?php if ($selectedTicket): ?>
        <div class="rocky-chat-head" id="rockyChatHead">
            <div>
                <h3 id="rockyHeadTitle"><?= htmlspecialchars($selectedTicket['ticket_id']) ?></h3>
                <p id="rockyHeadMeta">
                    <?= htmlspecialchars($selectedTicket['customer_name']) ?>
                    · <?= htmlspecialchars($selectedTicket['category']) ?>
                    · Agent: <?= htmlspecialchars($selectedTicket['agent']) ?>
                    · <?= htmlspecialchars($selectedTicket['support_channel']) ?>
                </p>
            </div>
            <span class="rocky-sim-badge"><i class="fas fa-flask"></i> Simulation</span>
        </div>
        <div class="rocky-chat-body" id="rockyChatBody">
            <?= rockyChatToHtml($chatMessages) ?>
        </div>
        <div class="rocky-label-bar">
            <p><strong>Rate this conversation</strong> — train Rocky.AI (demo only)</p>
            <div class="rocky-label-btns">
                <button type="button" class="rocky-label-btn rocky-label-btn--good" data-label="Good">
                    <i class="fas fa-thumbs-up"></i> Good
                </button>
                <button type="button" class="rocky-label-btn rocky-label-btn--bad" data-label="Bad">
                    <i class="fas fa-thumbs-down"></i> Bad
                </button>
                <button type="button" class="rocky-label-btn rocky-label-btn--improve" data-label="Need improvement">
                    <i class="fas fa-wrench"></i> Need improvement
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="rocky-empty">
            <i class="fas fa-comments"></i>
            <h3>Select a ticket</h3>
            <p>Choose a demo ticket from the left to view the chat transcript.</p>
        </div>
        <?php endif; ?>
    </section>
</div>

<div class="rocky-toast" id="rockyToast"></div>

<script>
(function() {
    const list = document.getElementById('rockyTicketList');
    const chatBody = document.getElementById('rockyChatBody');
    const headTitle = document.getElementById('rockyHeadTitle');
    const headMeta = document.getElementById('rockyHeadMeta');
    const toast = document.getElementById('rockyToast');
    const labelBtns = document.querySelectorAll('.rocky-label-btn');
    let toastTimer = null;

    function showToast(msg) {
        toast.textContent = msg;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
    }

    function clearLabels() {
        labelBtns.forEach(b => b.classList.remove('selected'));
    }

    labelBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            clearLabels();
            btn.classList.add('selected');
            showToast('Label "' + btn.dataset.label + '" recorded (simulation only)');
        });
    });

    list?.addEventListener('click', async (e) => {
        const item = e.target.closest('.rocky-ticket-item');
        if (!item) return;

        const ticketId = item.dataset.ticketId;
        list.querySelectorAll('.rocky-ticket-item').forEach(el => el.classList.remove('active'));
        item.classList.add('active');
        clearLabels();

        if (chatBody) chatBody.innerHTML = '<p style="text-align:center;color:#94A3B8;padding:40px;"><i class="fas fa-circle-notch fa-spin"></i> Loading conversation…</p>';

        try {
            const res = await fetch('rocky_chat.php?ticket_id=' + encodeURIComponent(ticketId), { credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load chat');

            if (headTitle) headTitle.textContent = data.ticket.ticket_id;
            if (headMeta) {
                headMeta.textContent = data.ticket.customer_name + ' · ' + data.ticket.category
                    + ' · Agent: ' + data.ticket.agent + ' · ' + data.ticket.channel;
            }
            if (chatBody) chatBody.innerHTML = data.html;
            const url = new URL(window.location.href);
            url.searchParams.set('ticket', ticketId);
            history.replaceState(null, '', url.pathname + url.search);
        } catch (err) {
            if (chatBody) chatBody.innerHTML = '<p style="color:#B91C1C;text-align:center;padding:40px;">' + err.message + '</p>';
        }
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_end.php'; ?>
