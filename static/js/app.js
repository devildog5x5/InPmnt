const appEl = document.getElementById("app");
const toastHost = document.getElementById("toast-host");
const modalRoot = document.getElementById("modal-root");

const money = (n) =>
  new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" }).format(Number(n || 0));

const fmtDate = (d) => {
  if (!d) return "—";
  const [y, m, day] = String(d).slice(0, 10).split("-");
  return `${m}/${day}/${y}`;
};

function toast(message) {
  const el = document.createElement("div");
  el.className = "toast";
  el.textContent = message;
  toastHost.appendChild(el);
  setTimeout(() => el.remove(), 3200);
}

async function api(path, options = {}) {
  const to = (window.__INPMNT__ && window.__INPMNT__.path) ? window.__INPMNT__.path(path) : path;
  const res = await fetch(to, {
    headers: { "Content-Type": "application/json", ...(options.headers || {}) },
    ...options,
  });
  if (res.status === 401) {
    location.href = (window.__INPMNT__ && window.__INPMNT__.path) ? window.__INPMNT__.path("/login") : "/login";
    throw new Error("Unauthorized");
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || "Request failed");
  return data;
}

function setActiveNav(route) {
  document.querySelectorAll("#main-nav a").forEach((a) => {
    const r = a.getAttribute("data-route");
    a.classList.toggle("active", r === route || (route.startsWith(r) && r !== "/"));
    if (route === "/" && r === "/") a.classList.add("active");
  });
}

function topbar({ eyebrow, title, subtitle, actions = "" }) {
  return `
    <div class="topbar">
      <div>
        <p class="eyebrow">${eyebrow}</p>
        <h1>${title}</h1>
        <p>${subtitle}</p>
      </div>
      <div class="actions">${actions}</div>
    </div>
  `;
}

function badge(status) {
  const map = {
    draft: "",
    sent: "",
    partial: "warn",
    overdue: "danger",
    paid: "ok",
    due: "warn",
    pending: "",
    cancelled: "warn",
  };
  return `<span class="badge ${map[status] || ""}">${status}</span>`;
}

function closeModal() {
  modalRoot.classList.remove("open");
  modalRoot.innerHTML = "";
}

function openModal({ title, lead = "", body, onMount }) {
  modalRoot.innerHTML = `
    <div class="modal" role="dialog" aria-modal="true">
      <h2>${title}</h2>
      ${lead ? `<p class="modal-lead">${lead}</p>` : ""}
      ${body}
    </div>
  `;
  modalRoot.classList.add("open");
  modalRoot.onclick = (e) => {
    if (e.target === modalRoot) closeModal();
  };
  if (onMount) onMount(modalRoot.querySelector(".modal"));
}

function empty(msg) {
  return `<div class="empty">${msg}</div>`;
}

/* ---------- Dashboard ---------- */
async function renderDashboard() {
  const data = await api("/api/dashboard");
  const { kpis, aging, open_invoices, due_reminders, activity } = data;
  const maxAging = Math.max(aging.current, aging.d1_30, aging.d31_60, aging.d60_plus, 1);

  appEl.innerHTML = `
    ${topbar({
      eyebrow: "Overview",
      title: "Collections dashboard",
      subtitle: "See what is overdue, what is due soon, and which reminders to send today.",
      actions: `
        <button class="btn secondary" id="btn-send-due">Send due reminders</button>
        <button class="btn" id="btn-new-inv">New invoice</button>
      `,
    })}
    <div class="grid-kpi">
      <div class="kpi alert">
        <div class="kpi-label">Overdue</div>
        <div class="kpi-value">${money(kpis.overdue_total)}</div>
        <div class="kpi-meta">${kpis.overdue_count} invoices past due</div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Open balance</div>
        <div class="kpi-value">${money(kpis.open_total)}</div>
        <div class="kpi-meta">${kpis.open_count} active invoices</div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Due in 7 days</div>
        <div class="kpi-value">${kpis.due_soon_count}</div>
        <div class="kpi-meta">Nudge before they slip</div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Collected · 30d</div>
        <div class="kpi-value">${money(kpis.collected_30)}</div>
        <div class="kpi-meta">${kpis.recovered_invoices_30} marked paid</div>
      </div>
    </div>
    <div class="panel-grid">
      <div class="panel">
        <div class="panel-header">
          <h2>Open invoices</h2>
          <button class="btn ghost sm" data-go="#/invoices">View all</button>
        </div>
        <div class="table-wrap">
          ${
            open_invoices.length
              ? `<table>
                  <thead><tr><th>Invoice</th><th>Client</th><th>Due</th><th>Balance</th><th>Status</th></tr></thead>
                  <tbody>
                    ${open_invoices
                      .map(
                        (i) => `<tr>
                          <td class="mono">${i.number}<span class="cell-sub">${i.title}</span></td>
                          <td>${i.client_name}</td>
                          <td>${fmtDate(i.due_date)}</td>
                          <td class="mono">${money(i.balance)}</td>
                          <td>${badge(i.status)}</td>
                        </tr>`
                      )
                      .join("")}
                  </tbody>
                </table>`
              : empty("No open invoices. Nice work.")
          }
        </div>
      </div>
      <div class="panel">
        <div class="panel-header"><h2>Aging</h2></div>
        <div class="aging-bars">
          ${agingRow("Current", aging.current, maxAging, "")}
          ${agingRow("1–30", aging.d1_30, maxAging, "warn")}
          ${agingRow("31–60", aging.d31_60, maxAging, "warn")}
          ${agingRow("60+", aging.d60_plus, maxAging, "danger")}
        </div>
      </div>
    </div>
    <div class="panel-grid" style="margin-top:14px">
      <div class="panel">
        <div class="panel-header">
          <h2>Reminder queue</h2>
          <button class="btn ghost sm" data-go="#/reminders">Open queue</button>
        </div>
        <div class="stack">
          ${
            due_reminders.length
              ? due_reminders
                  .map(
                    (r) => `
              <div class="reminder-card ${r.severity || ""}">
                <div class="reminder-meta">
                  <span>${r.channel} · ${fmtDate(r.scheduled_for)}</span>
                  <span>${money(r.balance)}</span>
                </div>
                <h3>${r.client_name} · ${r.invoice_number}</h3>
                <p>${r.subject || "Payment reminder"}</p>
                <div class="row-actions">
                  <button class="btn sm" data-send-reminder="${r.id}">Send now</button>
                  <button class="btn secondary sm" data-go="#/invoices/${r.invoice_id}">Invoice</button>
                </div>
              </div>`
                  )
                  .join("")
              : empty("No reminders due. You're caught up.")
          }
        </div>
      </div>
      <div class="panel">
        <div class="panel-header"><h2>Recent activity</h2></div>
        <div class="stack">
          ${
            activity.length
              ? activity
                  .map(
                    (a) => `
              <div class="reminder-card">
                <div class="reminder-meta"><span>${a.kind}</span><span>${fmtDate(a.created_at)}</span></div>
                <p style="margin:0">${a.message}</p>
              </div>`
                  )
                  .join("")
              : empty("No activity yet.")
          }
        </div>
      </div>
    </div>
  `;

  appEl.querySelector("#btn-new-inv")?.addEventListener("click", () => openInvoiceModal());
  appEl.querySelector("#btn-send-due")?.addEventListener("click", async () => {
    const res = await api("/api/reminders/send-due", { method: "POST", body: "{}" });
    toast(`Sent ${res.sent} reminder${res.sent === 1 ? "" : "s"}`);
    renderDashboard();
  });
  wireCommon(appEl);
}

function agingRow(label, value, max, cls) {
  const pct = Math.round((value / max) * 100);
  return `<div class="aging-row">
    <span>${label}</span>
    <div class="bar ${cls}"><i style="width:${pct}%"></i></div>
    <strong>${money(value)}</strong>
  </div>`;
}

/* ---------- Invoices ---------- */
async function renderInvoices(filter = "all") {
  const invoices = await api(`/api/invoices?status=${filter}`);
  appEl.innerHTML = `
    ${topbar({
      eyebrow: "Collections",
      title: "Invoices",
      subtitle: "Track balances, schedule reminders, and record payments.",
      actions: `<button class="btn" id="btn-new-inv">New invoice</button>`,
    })}
    <div class="toolbar">
      <div class="filters">
        <select id="status-filter">
          ${["all", "draft", "sent", "partial", "overdue", "paid"]
            .map((s) => `<option value="${s}" ${s === filter ? "selected" : ""}>${s}</option>`)
            .join("")}
        </select>
      </div>
    </div>
    <div class="panel">
      <div class="table-wrap">
        ${
          invoices.length
            ? `<table>
                <thead>
                  <tr>
                    <th>Number</th><th>Client</th><th>Issue</th><th>Due</th>
                    <th>Amount</th><th>Balance</th><th>Status</th><th></th>
                  </tr>
                </thead>
                <tbody>
                  ${invoices
                    .map(
                      (i) => `<tr>
                        <td class="mono"><a href="#/invoices/${i.id}">${i.number}</a>
                          <span class="cell-sub">${i.title}</span></td>
                        <td>${i.client_name}</td>
                        <td>${fmtDate(i.issue_date)}</td>
                        <td>${fmtDate(i.due_date)}</td>
                        <td class="mono">${money(i.amount)}</td>
                        <td class="mono">${money(i.balance)}</td>
                        <td>${badge(i.status)}</td>
                        <td class="row-actions">
                          ${
                            i.status !== "paid" && i.status !== "draft"
                              ? `<button class="btn sm secondary" data-pay="${i.id}">Pay</button>`
                              : ""
                          }
                          ${
                            i.status === "draft"
                              ? `<button class="btn sm" data-send-inv="${i.id}">Send</button>`
                              : ""
                          }
                        </td>
                      </tr>`
                    )
                    .join("")}
                </tbody>
              </table>`
            : empty("No invoices match this filter.")
        }
      </div>
    </div>
  `;
  appEl.querySelector("#btn-new-inv").onclick = () => openInvoiceModal();
  appEl.querySelector("#status-filter").onchange = (e) => renderInvoices(e.target.value);
  appEl.querySelectorAll("[data-pay]").forEach((btn) =>
    btn.addEventListener("click", () => openPaymentModal(+btn.dataset.pay))
  );
  appEl.querySelectorAll("[data-send-inv]").forEach((btn) =>
    btn.addEventListener("click", async () => {
      await api(`/api/invoices/${btn.dataset.sendInv}/send`, { method: "POST", body: "{}" });
      toast("Invoice sent — reminders scheduled");
      renderInvoices(filter);
    })
  );
}

async function renderInvoiceDetail(id) {
  const inv = await api(`/api/invoices/${id}`);
  appEl.innerHTML = `
    ${topbar({
      eyebrow: "Invoice",
      title: inv.number,
      subtitle: `${inv.title} · ${inv.client_name}`,
      actions: `
        <button class="btn secondary" data-go="#/invoices">Back</button>
        ${inv.status === "draft" ? `<button class="btn" id="btn-send">Mark sent</button>` : ""}
        ${inv.status !== "paid" && inv.status !== "draft" ? `<button class="btn" id="btn-pay">Record payment</button>` : ""}
        ${inv.status === "overdue" || inv.status === "partial" ? `<button class="btn danger" id="btn-final">Final notice</button>` : ""}
      `,
    })}
    <div class="grid-kpi">
      <div class="kpi"><div class="kpi-label">Amount</div><div class="kpi-value">${money(inv.amount)}</div></div>
      <div class="kpi"><div class="kpi-label">Paid</div><div class="kpi-value">${money(inv.amount_paid)}</div></div>
      <div class="kpi ${inv.balance > 0 ? "alert" : ""}"><div class="kpi-label">Balance</div><div class="kpi-value">${money(inv.balance)}</div></div>
      <div class="kpi"><div class="kpi-label">Status</div><div class="kpi-value" style="font-size:1.4rem;margin-top:14px">${badge(inv.status)}</div>
        <div class="kpi-meta">Due ${fmtDate(inv.due_date)}</div></div>
    </div>
    <div class="panel-grid">
      <div class="panel">
        <div class="panel-header"><h2>Details</h2></div>
        <div class="form-grid">
          <div class="field"><label>Client</label><div>${inv.client_name}${inv.client_company ? ` · ${inv.client_company}` : ""}</div></div>
          <div class="field"><label>Contact</label><div>${inv.client_email || "—"}<span class="cell-sub">${inv.client_phone || ""}</span></div></div>
          <div class="field"><label>Issue date</label><div>${fmtDate(inv.issue_date)}</div></div>
          <div class="field"><label>Due date</label><div>${fmtDate(inv.due_date)}</div></div>
          <div class="field full"><label>Notes</label><div class="muted">${inv.notes || "—"}</div></div>
        </div>
      </div>
      <div class="panel">
        <div class="panel-header"><h2>Payments</h2></div>
        ${
          inv.payments?.length
            ? `<div class="table-wrap"><table>
                <thead><tr><th>Date</th><th>Method</th><th>Amount</th></tr></thead>
                <tbody>${inv.payments
                  .map(
                    (p) => `<tr><td>${fmtDate(p.paid_at)}</td><td>${p.method || "—"}</td><td class="mono">${money(p.amount)}</td></tr>`
                  )
                  .join("")}</tbody></table></div>`
            : empty("No payments recorded.")
        }
      </div>
    </div>
    <div class="panel spaced">
      <div class="panel-header"><h2>Reminder schedule</h2></div>
      <div class="stack">
        ${
          inv.reminders?.length
            ? inv.reminders
                .map(
                  (r) => `
            <div class="reminder-card ${r.status === "due" ? "warning" : ""}">
              <div class="reminder-meta">
                <span>${r.channel} · ${fmtDate(r.scheduled_for)}</span>
                ${badge(r.status)}
              </div>
              <h3>${r.subject || "Reminder"}</h3>
              <p>${r.body}</p>
              ${
                r.status === "pending" || r.status === "due"
                  ? `<button class="btn sm" data-send-reminder="${r.id}">Send now</button>`
                  : ""
              }
            </div>`
                )
                .join("")
            : empty("No reminders scheduled. Mark the invoice as sent to generate a schedule.")
        }
      </div>
    </div>
  `;
  wireCommon(appEl);
  appEl.querySelector("#btn-send")?.addEventListener("click", async () => {
    await api(`/api/invoices/${id}/send`, { method: "POST", body: "{}" });
    toast("Invoice sent — reminders scheduled");
    renderInvoiceDetail(id);
  });
  appEl.querySelector("#btn-pay")?.addEventListener("click", () => openPaymentModal(id, () => renderInvoiceDetail(id)));
  appEl.querySelector("#btn-final")?.addEventListener("click", async () => {
    await api(`/api/invoices/${id}/final-notice`, { method: "POST", body: "{}" });
    toast("Final notice sent");
    renderInvoiceDetail(id);
  });
}

async function openInvoiceModal() {
  const clients = await api("/api/clients");
  const today = new Date().toISOString().slice(0, 10);
  const due = new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10);
  openModal({
    title: "New invoice",
    lead: "Create an invoice. Mark it sent to schedule reminders.",
    body: `
      <form id="inv-form" class="form-grid">
        <div class="field"><label>Client</label>
          <select name="client_id" required>
            <option value="">Select client…</option>
            ${clients.map((c) => `<option value="${c.id}">${c.name}${c.company ? ` · ${c.company}` : ""}</option>`).join("")}
          </select>
        </div>
        <div class="field"><label>Amount</label><input name="amount" type="number" min="0.01" step="0.01" required placeholder="0.00" /></div>
        <div class="field full"><label>Title / description</label><input name="title" required placeholder="Job or service description" /></div>
        <div class="field"><label>Issue date</label><input name="issue_date" type="date" value="${today}" /></div>
        <div class="field"><label>Due date</label><input name="due_date" type="date" value="${due}" /></div>
        <div class="field"><label>Status</label>
          <select name="status"><option value="draft">Draft</option><option value="sent">Send now</option></select>
        </div>
        <div class="field"><label>Notes</label><input name="notes" placeholder="Optional" /></div>
        <div class="modal-actions field full">
          <button type="button" class="btn secondary" id="m-cancel">Cancel</button>
          <button type="submit" class="btn">Create</button>
        </div>
      </form>
    `,
    onMount: (modal) => {
      modal.querySelector("#m-cancel").onclick = closeModal;
      modal.querySelector("#inv-form").onsubmit = async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const payload = Object.fromEntries(fd.entries());
        payload.client_id = +payload.client_id;
        payload.amount = +payload.amount;
        const inv = await api("/api/invoices", { method: "POST", body: JSON.stringify(payload) });
        closeModal();
        toast(`Created ${inv.number}`);
        location.hash = `#/invoices/${inv.id}`;
      };
    },
  });
}

function openPaymentModal(invoiceId, after) {
  openModal({
    title: "Record payment",
    lead: "Partial payments keep the invoice open and reminders active.",
    body: `
      <form id="pay-form" class="form-grid">
        <div class="field"><label>Amount</label><input name="amount" type="number" min="0.01" step="0.01" required /></div>
        <div class="field"><label>Method</label>
          <select name="method"><option>ACH</option><option>Card</option><option>Check</option><option>Cash</option><option>Other</option></select>
        </div>
        <div class="field"><label>Paid on</label><input name="paid_at" type="date" value="${new Date().toISOString().slice(0, 10)}" /></div>
        <div class="field"><label>Note</label><input name="note" placeholder="Optional" /></div>
        <div class="modal-actions field full">
          <button type="button" class="btn secondary" id="m-cancel">Cancel</button>
          <button type="submit" class="btn">Save payment</button>
        </div>
      </form>
    `,
    onMount: (modal) => {
      modal.querySelector("#m-cancel").onclick = closeModal;
      modal.querySelector("#pay-form").onsubmit = async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const payload = Object.fromEntries(fd.entries());
        payload.amount = +payload.amount;
        await api(`/api/invoices/${invoiceId}/payments`, { method: "POST", body: JSON.stringify(payload) });
        closeModal();
        toast("Payment recorded");
        if (after) after();
        else route();
      };
    },
  });
}

/* ---------- Clients ---------- */
async function renderClients() {
  const clients = await api("/api/clients");
  appEl.innerHTML = `
    ${topbar({
      eyebrow: "Collections",
      title: "Clients",
      subtitle: "People and businesses you invoice.",
      actions: `<button class="btn" id="btn-new-client">Add client</button>`,
    })}
    <div class="toolbar">
      <div class="filters"><input type="search" id="client-q" placeholder="Search clients…" /></div>
    </div>
    <div class="panel">
      <div class="table-wrap" id="client-table"></div>
    </div>
  `;
  const paint = (rows) => {
    const el = appEl.querySelector("#client-table");
    el.innerHTML = rows.length
      ? `<table>
          <thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Open balance</th><th>Invoices</th></tr></thead>
          <tbody>${rows
            .map(
              (c) => `<tr>
                <td>${c.name}</td>
                <td>${c.company || "—"}</td>
                <td>${c.email || "—"}</td>
                <td>${c.phone || "—"}</td>
                <td class="mono">${money(c.open_balance)}</td>
                <td>${c.invoice_count}</td>
              </tr>`
            )
            .join("")}</tbody></table>`
      : empty("No clients yet.");
  };
  paint(clients);
  appEl.querySelector("#client-q").oninput = (e) => {
    const q = e.target.value.toLowerCase();
    paint(
      clients.filter(
        (c) =>
          (c.name || "").toLowerCase().includes(q) ||
          (c.company || "").toLowerCase().includes(q) ||
          (c.email || "").toLowerCase().includes(q)
      )
    );
  };
  appEl.querySelector("#btn-new-client").onclick = () => {
    openModal({
      title: "Add client",
      body: `
        <form id="client-form" class="form-grid">
          <div class="field"><label>Name</label><input name="name" required /></div>
          <div class="field"><label>Company</label><input name="company" /></div>
          <div class="field"><label>Email</label><input name="email" type="email" /></div>
          <div class="field"><label>Phone</label><input name="phone" /></div>
          <div class="field full"><label>Notes</label><textarea name="notes"></textarea></div>
          <div class="modal-actions field full">
            <button type="button" class="btn secondary" id="m-cancel">Cancel</button>
            <button class="btn" type="submit">Save</button>
          </div>
        </form>
      `,
      onMount: (modal) => {
        modal.querySelector("#m-cancel").onclick = closeModal;
        modal.querySelector("#client-form").onsubmit = async (e) => {
          e.preventDefault();
          const payload = Object.fromEntries(new FormData(e.target).entries());
          await api("/api/clients", { method: "POST", body: JSON.stringify(payload) });
          closeModal();
          toast("Client added");
          renderClients();
        };
      },
    });
  };
}

/* ---------- Reminders ---------- */
async function renderReminders() {
  const rows = await api("/api/reminders?status=queue");
  appEl.innerHTML = `
    ${topbar({
      eyebrow: "Overview",
      title: "Reminder queue",
      subtitle: "Due and upcoming payment reminders. Send individually or clear the whole queue.",
      actions: `<button class="btn" id="btn-send-due">Send all due</button>`,
    })}
    <div class="panel">
      <div class="stack">
        ${
          rows.length
            ? rows
                .map(
                  (r) => `
            <div class="reminder-card ${r.severity || ""}">
              <div class="reminder-meta">
                <span>${r.channel} · scheduled ${fmtDate(r.scheduled_for)}</span>
                ${badge(r.status)}
              </div>
              <h3>${r.client_name} · ${r.invoice_number}</h3>
              <p>${r.subject || ""} — balance ${money(r.balance)}, due ${fmtDate(r.due_date)}</p>
              <div class="row-actions">
                ${(r.status === "pending" || r.status === "due")
                  ? `<button class="btn sm" data-send-reminder="${r.id}">Send now</button>`
                  : ""}
                <button class="btn secondary sm" data-go="#/invoices/${r.invoice_id}">Open invoice</button>
              </div>
            </div>`
                )
                .join("")
            : empty("Queue is clear.")
        }
      </div>
    </div>
  `;
  appEl.querySelector("#btn-send-due").onclick = async () => {
    const res = await api("/api/reminders/send-due", { method: "POST", body: "{}" });
    toast(`Sent ${res.sent} reminder${res.sent === 1 ? "" : "s"}`);
    renderReminders();
  };
  wireCommon(appEl);
}

/* ---------- Templates ---------- */
async function renderTemplates() {
  const templates = await api("/api/templates");
  appEl.innerHTML = `
    ${topbar({
      eyebrow: "Workspace",
      title: "Message templates",
      subtitle: "Use {{client_name}}, {{number}}, {{amount_due}}, {{due_date}}, {{business_name}}.",
    })}
    <div class="stack">
      ${templates
        .map(
          (t) => `
        <div class="panel">
          <div class="panel-header">
            <h2>${t.name} ${badge(t.channel)}${t.is_default ? " · default" : ""}</h2>
            <button class="btn ghost sm" data-edit-tmpl="${t.id}">Edit</button>
          </div>
          ${t.subject ? `<p class="muted" style="margin:0 0 8px"><strong>Subject:</strong> ${t.subject}</p>` : ""}
          <pre style="white-space:pre-wrap;font-family:var(--font-body);margin:0;color:var(--ink-soft);font-size:0.92rem;line-height:1.5">${t.body}</pre>
        </div>`
        )
        .join("")}
    </div>
  `;
  appEl.querySelectorAll("[data-edit-tmpl]").forEach((btn) => {
    btn.onclick = () => {
      const t = templates.find((x) => x.id === +btn.dataset.editTmpl);
      openModal({
        title: `Edit · ${t.name}`,
        body: `
          <form id="tmpl-form" class="form-grid">
            <div class="field"><label>Name</label><input name="name" value="${t.name}" required /></div>
            <div class="field"><label>Channel</label>
              <select name="channel"><option ${t.channel === "email" ? "selected" : ""}>email</option><option ${t.channel === "sms" ? "selected" : ""}>sms</option></select>
            </div>
            <div class="field full"><label>Subject</label><input name="subject" value="${t.subject || ""}" /></div>
            <div class="field full"><label>Body</label><textarea name="body" style="min-height:180px" required>${t.body}</textarea></div>
            <div class="modal-actions field full">
              <button type="button" class="btn secondary" id="m-cancel">Cancel</button>
              <button class="btn" type="submit">Save</button>
            </div>
          </form>
        `,
        onMount: (modal) => {
          modal.querySelector("#m-cancel").onclick = closeModal;
          modal.querySelector("#tmpl-form").onsubmit = async (e) => {
            e.preventDefault();
            const payload = Object.fromEntries(new FormData(e.target).entries());
            await api(`/api/templates/${t.id}`, { method: "PUT", body: JSON.stringify(payload) });
            closeModal();
            toast("Template saved");
            renderTemplates();
          };
        },
      });
    };
  });
}

/* ---------- Settings ---------- */
async function renderSettings() {
  const [s, billing] = await Promise.all([api("/api/settings"), api("/api/billing/status")]);
  appEl.innerHTML = `
    ${topbar({
      eyebrow: "Workspace",
      title: "Settings",
      subtitle: "Business profile, reminder cadence, and billing.",
      actions: `<a class="btn secondary" href="${window.__INPMNT__.logoutUrl}">Log out</a>`,
    })}
    <div class="panel" style="margin-bottom:14px">
      <div class="panel-header"><h2>Billing</h2></div>
      <p class="settings-note" style="margin-bottom:14px">
        Plan: <strong>${billing.plan}</strong>
        ${billing.trial_ends_on ? ` · trial ends ${fmtDate(billing.trial_ends_on)}` : ""}.
        ${billing.enabled ? "Stripe Checkout is configured." : "Stripe keys missing — add them to .env (see .env.example)."}
      </p>
      <div class="actions">
        <button class="btn secondary sm" data-plan="starter">Starter $19</button>
        <button class="btn sm" data-plan="pro">Pro $39</button>
        <button class="btn secondary sm" data-plan="annual">Annual $99</button>
        ${billing.has_customer ? `<button class="btn ghost sm" id="btn-portal">Manage billing</button>` : ""}
      </div>
    </div>
    <form id="settings-form" class="panel form-grid">
      <div class="field"><label>Business name</label><input name="business_name" value="${s.business_name || ""}" required /></div>
      <div class="field"><label>Owner</label><input name="owner_name" value="${s.owner_name || ""}" required /></div>
      <div class="field"><label>Email</label><input name="email" type="email" value="${s.email || ""}" required /></div>
      <div class="field"><label>Phone</label><input name="phone" value="${s.phone || ""}" /></div>
      <div class="field"><label>Website</label><input name="website" value="${s.website || ""}" /></div>
      <div class="field"><label>Currency</label><input name="currency" value="${s.currency || "USD"}" /></div>
      <div class="field"><label>Default channel</label>
        <select name="default_channel">
          <option value="email" ${s.default_channel === "email" ? "selected" : ""}>Email</option>
          <option value="sms" ${s.default_channel === "sms" ? "selected" : ""}>SMS</option>
        </select>
      </div>
      <div class="field"><label>Reminder offsets (days vs due)</label>
        <input name="reminder_offsets" value="${(s.reminder_offsets || []).join(", ")}" placeholder="-3, 0, 3, 7, 14" />
      </div>
      <div class="field full">
        <p class="settings-note">
          Reminder sends are logged in-app until SMTP/Twilio are connected (see GO_TO_MARKET.md).
        </p>
      </div>
      <div class="field full actions">
        <button class="btn" type="submit">Save settings</button>
      </div>
    </form>
  `;
  document.getElementById("workspace-label").textContent =
    `${s.business_name} · ${s.plan === "trial" ? "Trial" : s.plan}`;
  appEl.querySelector("#settings-form").onsubmit = async (e) => {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(e.target).entries());
    await api("/api/settings", { method: "PUT", body: JSON.stringify(payload) });
    toast("Settings saved");
    renderSettings();
  };
  appEl.querySelectorAll("[data-plan]").forEach((btn) => {
    btn.onclick = async () => {
      try {
        const res = await api("/api/billing/checkout", {
          method: "POST",
          body: JSON.stringify({ plan: btn.dataset.plan }),
        });
        if (res.url) location.href = res.url;
      } catch (err) {
        toast(err.message || "Checkout unavailable — configure Stripe in .env");
      }
    };
  });
  appEl.querySelector("#btn-portal")?.addEventListener("click", async () => {
    try {
      const res = await api("/api/billing/portal", { method: "POST", body: "{}" });
      if (res.url) location.href = res.url;
    } catch (err) {
      toast(err.message || "Portal unavailable");
    }
  });
}

function wireCommon(root) {
  root.querySelectorAll("[data-go]").forEach((el) => {
    el.addEventListener("click", () => {
      location.hash = el.getAttribute("data-go");
    });
  });
  root.querySelectorAll("[data-send-reminder]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      await api(`/api/reminders/${btn.dataset.sendReminder}/send`, { method: "POST", body: "{}" });
      toast("Reminder sent");
      route();
    });
  });
}

async function route() {
  const hash = location.hash.replace(/^#/, "") || "/";
  const path = hash.split("?")[0];
  setActiveNav(path.startsWith("/invoices/") ? "/invoices" : path);

  try {
    if (path === "/" || path === "") await renderDashboard();
    else if (path === "/invoices") await renderInvoices();
    else if (path.startsWith("/invoices/")) await renderInvoiceDetail(path.split("/")[2]);
    else if (path === "/clients") await renderClients();
    else if (path === "/reminders") await renderReminders();
    else if (path === "/templates") await renderTemplates();
    else if (path === "/settings") await renderSettings();
    else await renderDashboard();
  } catch (err) {
    console.error(err);
    toast(err.message || "Something went wrong");
  }
}

window.addEventListener("hashchange", route);
api("/api/me").then((me) => {
  if (me.settings) {
    document.getElementById("workspace-label").textContent =
      `${me.settings.business_name} · ${me.settings.plan === "trial" ? "Trial" : me.settings.plan}`;
  }
  route();
});
