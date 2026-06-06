// ================= SYSTEM DESKTOP STATE ENGINE =================
let globalZIndex = 20;
const activeWindows = new Set();

// Native Drag and Drop Management Variable Definitions
let dragElement = null;
let dragX = 0;
let dragY = 0;

// Initialize System Components on DOM Completion
document.addEventListener("DOMContentLoaded", () => {
    updateClock();
    setInterval(updateClock, 1000);
    
    // Auto-open primary showcase workspace window
    setTimeout(() => {
        openWindow('cursor-win');
    }, 400);

    // Boot up hardware dashboard telemetry monitoring loop
    initTelemetryMonitoring();

    // Sequentially trigger autonomous AI typing animation in background window
    setTimeout(executeAIAssistantTypingSimulation, 1500);
});

// Real-time Clock Subsystem
function updateClock() {
    const clockEl = document.getElementById('top-bar-clock');
    if (!clockEl) return;
    
    const now = new Date();
    const options = { weekday: 'short', hour: 'numeric', minute: '2-digit', hour12: true };
    clockEl.textContent = now.toLocaleDateString('en-US', options).replace(',', '');
}

// Window Controller Interface
function openWindow(id) {
    const win = document.getElementById(id);
    if (!win) return;

    globalZIndex++;
    win.style.zIndex = globalZIndex;
    
    if (!win.classList.contains('open')) {
        win.classList.add('open');
    }
    
    activeWindows.add(id);
    syncDockIndicators();
}

function closeWindow(id) {
    const win = document.getElementById(id);
    if (win) {
        win.classList.remove('open');
    }
    activeWindows.delete(id);
    syncDockIndicators();
}

// Synchronize Bottom Hardware Shelf Indicators
function syncDockIndicators() {
    // Map data relations to bind interface layouts
    const targetMap = {
        'projects-win': '.dock-prj',
        'domains-win': '.dock-dom',
        'cursor-win': '.dock-ide',
        'apps-win': '.dock-app',
        'ai-win': '.dock-ai',
        'task-manager': '.dock-tsk'
    };

    Object.keys(targetMap).forEach(winId => {
        const iconClass = targetMap[winId];
        const iconEl = document.querySelector(iconClass);
        if (iconEl) {
            const parentItem = iconEl.closest('.dock-icon-item');
            if (parentItem) {
                if (activeWindows.has(winId)) {
                    parentItem.classList.add('active-running');
                } else {
                    parentItem.classList.remove('active-running');
                }
            }
        }
    });
}

// ================= DRAG AND DROP RUNTIME INTERACTION =================
function dragStart(e, windowId) {
    // Prevent dragging when firing click events on layout configuration control rings
    if (e.target.classList.contains('control') || e.target.closest('.window-actions')) return;
    
    dragElement = document.getElementById(windowId);
    if (!dragElement) return;

    globalZIndex++;
    dragElement.style.zIndex = globalZIndex;

    const rect = dragElement.getBoundingClientRect();
    dragX = e.clientX - rect.left;
    dragY = e.clientY - rect.top;

    document.addEventListener('mousemove', dragPerform);
    document.addEventListener('mouseup', dragTerminate);
}

function dragPerform(e) {
    if (!dragElement) return;
    
    // Bounds clamping vectors
    let leftPosition = e.clientX - dragX;
    let topPosition = e.clientY - dragY;
    
    // Prevent title header scrolling beyond visible edge threshold
    if (topPosition < 32) topPosition = 32; 

    dragElement.style.left = `${leftPosition}px`;
    dragElement.style.top = `${topPosition}px`;
}

function dragTerminate() {
    document.removeEventListener('mousemove', dragPerform);
    document.removeEventListener('mouseup', dragTerminate);
    dragElement = null;
}

// ================= TELEMETRY SIMULATOR ENGINE =================
function initTelemetryMonitoring() {
    const cpuVal = document.getElementById('val-cpu');
    const cpuBar = document.getElementById('bar-cpu');
    const ramVal = document.getElementById('val-ram');
    const ramBar = document.getElementById('bar-ram');
    const netVal = document.getElementById('val-net');
    const netBar = document.getElementById('bar-net');

    setInterval(() => {
        // Generate pseudo-random organic usage oscillations
        if (cpuVal && cpuBar) {
            const cpu = Math.floor(28 + Math.random() * 16);
            cpuVal.textContent = `${cpu}%`;
            cpuBar.style.width = `${cpu}%`;
        }
        if (ramVal && ramBar) {
            const ram = Math.floor(70 + Math.random() * 4);
            ramVal.textContent = `${ram}%`;
            ramBar.style.width = `${ram}%`;
        }
        if (netVal && netBar) {
            const netMbs = (12.4 + Math.random() * 4.8).toFixed(1);
            netVal.textContent = `${netMbs} Mbps`;
            // map scale roughly to fill bar gauge representation
            netBar.style.width = `${Math.floor(netMbs * 4)}%`;
        }
    }, 2500);
}

// ================= STANDALONE AI GENERATION FLOW =================
function executeAIAssistantTypingSimulation() {
    const steps = ['step-h', 'step-c', 'step-j', 'step-u', 'step-box'];
    let initialDelay = 400;

    steps.forEach((stepId, index) => {
        setTimeout(() => {
            const element = document.getElementById(stepId);
            if (element) {
                element.classList.remove('transparent-step');
                // Auto scroll conversational index context down
                const chatContainer = document.getElementById('standalone-chat-history');
                if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        }, initialDelay + (index * 750)); 
    });
}

// ================= DOMAIN ORCHESTRATION PROTOTYPING =================
function openDomainModal() {
    // Reset wizard view sequence nodes
    document.getElementById('domain-modal-content-primary').classList.remove('hidden-step');
    document.getElementById('domain-modal-content-loading').classList.add('hidden-step');
    document.getElementById('domain-modal-content-success').classList.add('hidden-step');
    
    document.getElementById('modal-domain').classList.add('open');
}

function closeDomainModal() {
    document.getElementById('modal-domain').classList.remove('open');
}

function processDomainProvisioning() {
    const domainInput = document.getElementById('domain-input-field');
    let domainName = domainInput.value.trim() || "myportfolio.com";
    
    // Switch pipeline context panels
    document.getElementById('domain-modal-content-primary').classList.add('hidden-step');
    document.getElementById('domain-modal-content-loading').classList.remove('hidden-step');

    const loaderText = document.getElementById('domain-loading-text');
    const step1 = document.getElementById('d-step-1');
    const step2 = document.getElementById('d-step-2');
    const step3 = document.getElementById('d-step-3');

    // Reset timeline elements state markers
    [step1, step2, step3].forEach(el => {
        el.className = "timeline-step pending";
    });
    step1.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin step-icon-spin"></i> Instantiating SSL handshakes`;
    step2.innerHTML = `<i class="fa-solid fa-circle text-muted"></i> Configuring distributed DNS context`;
    step3.innerHTML = `<i class="fa-solid fa-circle text-muted"></i> Broadcast target live confirmation`;

    // Timeline Animation Loop Sequence
    setTimeout(() => {
        step1.className = "timeline-step completed";
        step1.innerHTML = `<i class="fa-solid fa-circle-check"></i> SSL Handshake secured across Edge endpoints`;
        
        step2.className = "timeline-step pending";
        step2.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin step-icon-spin"></i> Propagating global DNS zones...`;
        loaderText.textContent = "Writing infrastructure entry tables...";
    }, 1200);

    setTimeout(() => {
        step2.className = "timeline-step completed";
        step2.innerHTML = `<i class="fa-solid fa-circle-check"></i> Anycast entries acknowledged by Cloudflare networks`;
        
        step3.className = "timeline-step pending";
        step3.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin step-icon-spin"></i> verifying routing loop validation...`;
        loaderText.textContent = "Verifying health endpoint signals...";
    }, 2600);

    setTimeout(() => {
        step3.className = "timeline-step completed";
        step3.innerHTML = `<i class="fa-solid fa-circle-check"></i> Health check OK. Domain online tracking initialized`;
    }, 3800);

    setTimeout(() => {
        document.getElementById('domain-modal-content-loading').classList.add('hidden-step');
        const successPanel = document.getElementById('domain-modal-content-success');
        successPanel.classList.remove('hidden-step');
        
        const anchor = document.getElementById('final-domain-anchor');
        anchor.textContent = `https://${domainName}`;
        anchor.href = `https://${domainName}`;
    }, 4400);
}

// ================= PRODUCTION LIVE DEPLOYMENT PIPELINE =================
function openPublishModal(projectName) {
    document.getElementById('publish-project-name').textContent = projectName;
    
    // Reset view steps visibility maps
    document.getElementById('publish-modal-content-primary').classList.remove('hidden-step');
    document.getElementById('publish-modal-content-loading').classList.add('hidden-step');
    document.getElementById('publish-modal-content-success').classList.add('hidden-step');

    document.getElementById('modal-publish').classList.add('open');
}

function closePublishModal() {
    document.getElementById('modal-publish').classList.remove('open');
}

function executeProductionDeployment() {
    document.getElementById('publish-modal-content-primary').classList.add('hidden-step');
    document.getElementById('publish-modal-content-loading').classList.remove('hidden-step');

    const loaderText = document.getElementById('publish-loading-text');
    const p1 = document.getElementById('p-step-1');
    const p2 = document.getElementById('p-step-2');
    const p3 = document.getElementById('p-step-3');
    const p4 = document.getElementById('p-step-4');
    const p5 = document.getElementById('p-step-5');

    // Reset initial step states
    const steps = [p1, p2, p3, p4, p5];
    steps.forEach(s => s.className = "timeline-step pending");
    
    p1.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin step-icon-spin"></i> Building micro-frontend bundle`;
    p2.innerHTML = `<i class="fa-solid fa-circle text-muted"></i> Optimizing asset compression graphs`;
    p3.innerHTML = `<i class="fa-solid fa-circle text-muted"></i> Configuring edge secure sockets layer (SSL)`;
    p4.innerHTML = `<i class="fa-solid fa-circle text-muted"></i> Updating DNS global Anycast clusters`;
    p5.innerHTML = `<i class="fa-solid fa-circle text-muted"></i> Going Live verification status`;

    // Sequentially cascade deployment milestones
    setTimeout(() => {
        p1.className = "timeline-step completed";
        p1.innerHTML = `<i class="fa-solid fa-circle-check"></i> Frontend bundle compiled successfully [Size: 14.2 KB]`;
        p2.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin step-icon-spin"></i> Executing Brotli asset compression profiles...`;
        loaderText.textContent = "Optimizing system deliverability graphs...";
    }, 1000);

    setTimeout(() => {
        p2.className = "timeline-step completed";
        p2.innerHTML = `<i class="fa-solid fa-circle-check"></i> Assets optimized. Content delivery vectors mapped`;
        p3.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin step-icon-spin"></i> Provisioning target routing SSL parameters...`;
        loaderText.textContent = "Configuring edge security headers...";
    }, 2000);

    setTimeout(() => {
        p3.className = "timeline-step completed";
        p3.innerHTML = `<i class="fa-solid fa-circle-check"></i> Edge TLS/SSL handshake certificates generated successfully`;
        p4.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin step-icon-spin"></i> Broadcasting routes to global cache registers...`;
        loaderText.textContent = "Propagating system availability matrices...";
    }, 3200);

    setTimeout(() => {
        p4.className = "timeline-step completed";
        p4.innerHTML = `<i class="fa-solid fa-circle-check"></i> Cache propagation verified across 42 global POP servers`;
        p5.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin step-icon-spin"></i> Finalizing load balancer handshakes...`;
        loaderText.textContent = "Awaiting edge routing synchronization validation...";
    }, 4200);

    setTimeout(() => {
        p5.className = "timeline-step completed";
        p5.innerHTML = `<i class="fa-solid fa-circle-check"></i> Deployment payload verified online. Health score: 100%`;
    }, 4900);

    setTimeout(() => {
        document.getElementById('publish-modal-content-loading').classList.add('hidden-step');
        document.getElementById('publish-modal-content-success').classList.remove('hidden-step');
    }, 5400);
}

// ================= APP STORE VIRTUAL PROVISIONING SYSTEM =================
function toggleAppInstall(appShortId) {
    const btn = document.getElementById(`btn-app-${appShortId}`);
    if (!btn) return;

    if (btn.classList.contains('btn-secondary')) {
        // Trigger installation state animation sequence
        btn.disabled = true;
        btn.className = "btn btn-sm btn-secondary btn-full";
        btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Installing...`;
        
        setTimeout(() => {
            btn.disabled = false;
            btn.className = "btn btn-sm btn-primary btn-full";
            btn.innerHTML = `<i class="fa-solid fa-circle-check"></i> Installed &mdash; Launch`;
        }, 2000);
    } else {
        // App is already configured and installed; simulate running instance invocation
        openWindow('task-manager');
    }
}