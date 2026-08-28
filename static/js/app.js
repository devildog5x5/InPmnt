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

function toast(message, kind = "") {
  const el = document.createElement("div");
  el.className = kind ? `toast ${kind}` : "toast";
  el.textContent = message;
  toastHost.appendChild(el);
  setTimeout(() => el.remove(), kind === "error" ? 8000 : 3200);
}

async function api(path, options = {}) {
  const res = await fetch(path, {
    headers: { "Content-Type": "application/json", Accept: "application/json", ...(options.headers || {}) },
    ...options,
  });
  if (res.status === 401) {
    location.href = "/login";
    throw new Error("Unauthorized");
  }
  const ctype = (res.headers.get("content-type") || "").toLowerCase();
  const text = await res.text();
  let data = {};
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      throw new Error(
        "The server did not return JSON (often leftover WordPress). In public_html run bash remove-wordpress.sh, then hPanel → Cache → Purge All."
      );
    }
  }
  if (!ctype.includes("json") && res.ok) {
    throw new Error(
      "The server did not return JSON (often leftover WordPress). In public_html run bash remove-wordpress.sh, then hPanel → Cache → Purge All."
    );
  }
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

function esc(s) {
  return String(s ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

async function sendInvoiceNow(id) {
  const res = await api(`/api/invoices/${id}/send`, { method: "POST", body: "{}" });
  if (!res || !res.id || res.emailed !== true) {
    throw new Error(
      res?.error ||
        "Invoice was not emailed. Check Settings → Email delivery, and that the client has an email address."
    );
  }
  const via = res.mail_provider && res.mail_provider !== "fake" ? ` via ${res.mail_provider}` : "";
  toast(`Invoice emailed to ${res.client_email || "the client"}${via}`);
  return res;
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
                          <td class="mono">
                            <a href="#/invoices/${i.id}">${i.number}</a>
                            <span class="cell-sub">${i.title}</span>
                            <div class="row-actions" style="margin-top:8px">
                              <button type="button" class="btn sm" data-edit-inv="${i.id}">Edit invoice</button>
                            </div>
                          </td>
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
                  <button type="button" class="btn sm" data-edit-inv="${r.invoice_id}">Edit invoice</button>
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
    try {
      const res = await api("/api/reminders/send-due", { method: "POST", body: "{}" });
      toast(`Sent ${res.sent} reminder${res.sent === 1 ? "" : "s"}`);
      renderDashboard();
    } catch (err) {
      toast(err.message || "Send failed", "error");
    }
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
  const [invoices, mail] = await Promise.all([
    api(`/api/invoices?status=${filter}`),
    api("/api/mail/status").catch(() => ({ configured: false })),
  ]);
  appEl.innerHTML = `
    ${topbar({
      eyebrow: "Collections",
      title: "Invoices",
      subtitle: "Open any invoice and press Edit invoice — drafts, sent, and paid can all be changed.",
      actions: `<button class="btn" id="btn-new-inv">New invoice</button>`,
    })}
    ${
      mail.configured
        ? ""
        : `<div class="flash warn">Email is not configured, so <strong>Send now</strong> cannot deliver invoices.
            Open <a href="#/settings">Settings → Email delivery</a> and send a test email, or set SMTP in <code>.env</code>.</div>`
    }
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
                    <th>Amount</th><th>Balance</th><th>Status</th><th class="col-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${invoices
                    .map(
                      (i) => `<tr>
                        <td class="mono">
                          <a href="#/invoices/${i.id}">${i.number}</a>
                          <span class="cell-sub">${esc(i.title || "")}</span>
                          <div class="row-actions" style="margin-top:8px">
                            <button type="button" class="btn sm" data-edit-inv="${i.id}">Edit invoice</button>
                          </div>
                        </td>
                        <td>${i.client_name}</td>
                        <td>${fmtDate(i.issue_date)}</td>
                        <td>${fmtDate(i.due_date)}</td>
                        <td class="mono">${money(i.amount)}</td>
                        <td class="mono">${money(i.balance)}</td>
                        <td>${badge(i.status)}</td>
                        <td class="row-actions col-actions">
                          <button type="button" class="btn sm" data-edit-inv="${i.id}">Edit invoice</button>
                          ${
                            i.status === "draft"
                              ? `<button class="btn sm" data-send-inv="${i.id}">Send now</button>`
                              : `<button class="btn sm secondary" data-send-inv="${i.id}">Email</button>`
                          }
                          ${
                            i.status !== "paid" && i.status !== "draft"
                              ? `<button class="btn sm secondary" data-pay="${i.id}">Pay</button>`
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
      btn.disabled = true;
      try {
        await sendInvoiceNow(+btn.dataset.sendInv);
        // Leave the draft filter so the newly sent invoice stays visible.
        renderInvoices(filter === "draft" ? "all" : filter);
      } catch (err) {
        btn.disabled = false;
        toast(err.message || "Send failed", "error");
      }
    })
  );
}

async function renderInvoiceDetail(id) {
  const [inv, s] = await Promise.all([api(`/api/invoices/${id}`), api("/api/settings")]);
  const bizMeta = [s.owner_name, s.email, s.phone].filter(Boolean).join(" · ");
  appEl.innerHTML = `
    ${topbar({
      eyebrow: "Invoice",
      title: inv.number,
      subtitle: `${inv.title} · ${inv.client_name}`,
      actions: `
        <button type="button" class="btn" id="btn-edit" data-edit-inv="${inv.id}">Edit invoice</button>
        ${inv.status === "draft" ? `<button class="btn secondary" id="btn-send">Send now</button>` : `<button class="btn secondary" id="btn-send">Email invoice</button>`}
        <a class="btn secondary" href="/api/invoices/${id}/pdf">Download PDF</a>
        ${inv.status !== "paid" && inv.status !== "draft" ? `<button class="btn secondary" id="btn-pay">Record payment</button>` : ""}
        ${inv.status === "overdue" || inv.status === "partial" ? `<button class="btn danger" id="btn-final">Final notice</button>` : ""}
        <button class="btn secondary" data-go="#/invoices">Back</button>
      `,
    })}
    <div class="invoice-sheet">
      <div class="invoice-letterhead">
        <img class="invoice-logo" src="/static/img/inpmnt-icon.png" alt="Company logo" />
        <div class="invoice-letterhead-copy">
          <div class="invoice-biz">${s.business_name || "InPmnt"}</div>
          ${bizMeta ? `<div class="invoice-biz-meta">${bizMeta}</div>` : ""}
        </div>
        <div class="invoice-letterhead-right">
          <div class="invoice-word">INVOICE</div>
          <div class="mono">${inv.number}</div>
        </div>
      </div>
      <div class="invoice-edit-bar">
        <button type="button" class="btn" data-edit-inv="${inv.id}">Edit invoice</button>
        <span class="muted">Change the client, amount, dates, title, or notes — including after it has been sent.</span>
      </div>
    </div>
    <div class="grid-kpi">
      <div class="kpi" data-edit-inv="${inv.id}" role="button" tabindex="0"><div class="kpi-label">Amount</div><div class="kpi-value">${money(inv.amount)}</div></div>
      <div class="kpi"><div class="kpi-label">Paid</div><div class="kpi-value">${money(inv.amount_paid)}</div></div>
      <div class="kpi ${inv.balance > 0 ? "alert" : ""}"><div class="kpi-label">Balance</div><div class="kpi-value">${money(inv.balance)}</div></div>
      <div class="kpi"><div class="kpi-label">Status</div><div class="kpi-value" style="font-size:1.4rem;margin-top:14px">${badge(inv.status)}</div>
        <div class="kpi-meta">Due ${fmtDate(inv.due_date)}</div></div>
    </div>
    <div class="panel-grid">
      <div class="panel">
        <div class="panel-header">
          <h2>Details</h2>
          <button type="button" class="btn" data-edit-inv="${inv.id}">Edit invoice</button>
        </div>
        <div class="form-grid">
          <div class="field"><label>Client</label>
            <button type="button" class="edit-field" data-edit-inv="${inv.id}">
              ${esc(inv.client_name || "")}${inv.client_company ? ` · ${esc(inv.client_company)}` : ""}
              <span class="edit-hint">Change</span>
            </button>
          </div>
          <div class="field"><label>Contact</label>
            <button type="button" class="edit-field" data-edit-inv="${inv.id}">
              ${esc(inv.client_email || "—")}<span class="cell-sub">${esc(inv.client_phone || "")}</span>
              <span class="edit-hint">Change</span>
            </button>
          </div>
          <div class="field"><label>Issue date</label>
            <button type="button" class="edit-field" data-edit-inv="${inv.id}">
              ${fmtDate(inv.issue_date)}<span class="edit-hint">Change</span>
            </button>
          </div>
          <div class="field"><label>Due date</label>
            <button type="button" class="edit-field" data-edit-inv="${inv.id}">
              ${fmtDate(inv.due_date)}<span class="edit-hint">Change</span>
            </button>
          </div>
          <div class="field full"><label>Title / description</label>
            <button type="button" class="edit-field" data-edit-inv="${inv.id}">
              ${esc(inv.title || "—")}<span class="edit-hint">Change</span>
            </button>
          </div>
          <div class="field full"><label>Notes</label>
            <button type="button" class="edit-field" data-edit-inv="${inv.id}">
              ${esc(inv.notes || "—")}<span class="edit-hint">Change</span>
            </button>
          </div>
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
            : empty("No reminders scheduled. Send the invoice to email the client and generate a schedule.")
        }
      </div>
    </div>
  `;
  wireCommon(appEl);
  appEl.querySelector("#btn-send")?.addEventListener("click", async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    try {
      await sendInvoiceNow(id);
      renderInvoiceDetail(id);
    } catch (err) {
      btn.disabled = false;
      toast(err.message || "Send failed", "error");
    }
  });
  appEl.querySelector("#btn-pay")?.addEventListener("click", () => openPaymentModal(id, () => renderInvoiceDetail(id)));
  appEl.querySelector("#btn-final")?.addEventListener("click", async () => {
    try {
      await api(`/api/invoices/${id}/final-notice`, { method: "POST", body: "{}" });
      toast("Final notice sent");
      renderInvoiceDetail(id);
    } catch (err) {
      toast(err.message || "Send failed", "error");
    }
  });
}

async function openInvoiceEditor(invoiceId) {
  const id = Number(invoiceId);
  if (!id) return;
  try {
    const inv = await api(`/api/invoices/${id}`);
    if (!inv || !inv.id) throw new Error("Invoice not found");
    await openInvoiceModal(inv, () => {
      const path = (location.hash.replace(/^#/, "") || "/").split("?")[0];
      if (path === `/invoices/${id}`) renderInvoiceDetail(id);
      else route();
    });
  } catch (err) {
    toast(err.message || "Could not open invoice for editing", "error");
  }
}

async function openInvoiceModal(existing = null, after = null) {
  const clients = await api("/api/clients");
  const today = new Date().toISOString().slice(0, 10);
  const defaultDue = new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10);
  const isEdit = Boolean(existing && existing.id);
  const isDraft = !isEdit || existing.status === "draft";
  const saveLabel = isEdit ? "Save" : "Save draft";
  const sendLabel = isEdit && !isDraft ? "Save & email" : "Send now";
  const lead = isEdit
    ? existing.status === "paid"
      ? "Paid invoices can still be edited and emailed again."
      : isDraft
        ? "Update this invoice, then send now or send it from the Invoices page."
        : "Save changes for the next PDF, or save and email the updated invoice."
    : "Save as a draft and send later from Invoices, or send now to email the client a PDF.";
  const minAmt = existing ? Math.max(0.01, Number(existing.amount_paid || 0) || 0.01) : 0.01;
  const issueVal = existing?.issue_date ? String(existing.issue_date).slice(0, 10) : today;
  const dueVal = existing?.due_date ? String(existing.due_date).slice(0, 10) : defaultDue;
  const selectedClient = existing ? String(existing.client_id) : "";
  openModal({
    title: isEdit ? `Edit ${esc(existing.number)}` : "New invoice",
    lead,
    body: `
      <form id="inv-form" class="form-grid">
        <div class="field"><label>Client</label>
          <select name="client_id" required>
            <option value="">Select client…</option>
            ${clients
              .map(
                (c) =>
                  `<option value="${c.id}" ${String(c.id) === selectedClient ? "selected" : ""}>${esc(c.name)}${
                    c.company ? ` · ${esc(c.company)}` : ""
                  }</option>`
              )
              .join("")}
          </select>
        </div>
        <div class="field"><label>Amount</label>
          <input name="amount" type="number" min="${minAmt}" step="0.01" required placeholder="0.00"
            value="${existing ? esc(existing.amount) : ""}" />
        </div>
        <div class="field full"><label>Title / description</label>
          <input name="title" required placeholder="Job or service description" value="${esc(existing?.title || "")}" />
        </div>
        <div class="field"><label>Issue date</label><input name="issue_date" type="date" value="${issueVal}" /></div>
        <div class="field"><label>Due date</label><input name="due_date" type="date" value="${dueVal}" /></div>
        <div class="field full"><label>Notes</label>
          <input name="notes" placeholder="Optional" value="${esc(existing?.notes || "")}" />
        </div>
        ${
          isEdit && !isDraft
            ? `<p class="field full muted" style="margin:0">Save updates this invoice. <strong>Save &amp; email</strong> sends the new PDF to the client.</p>`
            : `<p class="field full muted" style="margin:0">Need to email later? Save the draft, then use <strong>Send now</strong> on the Invoices page.</p>`
        }
        <div class="modal-actions field full">
          <button type="button" class="btn secondary" id="m-cancel">Cancel</button>
          <button type="submit" class="btn secondary" id="m-save" data-intent="save">${saveLabel}</button>
          <button type="submit" class="btn" id="m-send" data-intent="send">${sendLabel}</button>
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
        const sendNow = (e.submitter && e.submitter.dataset && e.submitter.dataset.intent) === "send";
        const saveBtn = modal.querySelector("#m-save");
        const sendBtn = modal.querySelector("#m-send");
        saveBtn.disabled = true;
        sendBtn.disabled = true;
        try {
          let inv;
          if (isEdit) {
            payload.send = sendNow;
            inv = await api(`/api/invoices/${existing.id}`, {
              method: "PUT",
              body: JSON.stringify(payload),
            });
          } else {
            payload.status = sendNow ? "sent" : "draft";
            inv = await api("/api/invoices", { method: "POST", body: JSON.stringify(payload) });
          }
          if (!inv || !inv.id) {
            throw new Error(
              "Could not save invoice. If you still see WordPress, run bash remove-wordpress.sh in public_html and purge cache."
            );
          }
          if (sendNow && inv.emailed !== true) {
            throw new Error(
              "Invoice saved but was not emailed. Check Settings → Email delivery, and that the client has an email address."
            );
          }
          closeModal();
          if (isEdit) {
            toast(
              inv.emailed
                ? `Saved and emailed ${inv.number} to ${inv.client_email || "the client"}`
                : `Saved ${inv.number}`
            );
          } else {
            toast(
              inv.emailed
                ? `Emailed ${inv.number} to ${inv.client_email || "the client"}`
                : `Saved ${inv.number} as a draft — send it from Invoices when ready`
            );
          }
          if (typeof after === "function") after(inv);
          else location.hash = `#/invoices/${inv.id}`;
        } catch (err) {
          saveBtn.disabled = false;
          sendBtn.disabled = false;
          toast(err.message || (isEdit ? "Could not save invoice" : "Could not create invoice"), "error");
        }
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
async function openClientModal(existing = null) {
  const isEdit = Boolean(existing && existing.id);
  openModal({
    title: isEdit ? `Edit ${esc(existing.name)}` : "Add client",
    lead: isEdit
      ? "Update contact details. Invoice emails use this address."
      : "Save the client, then create invoices. You can edit them later.",
    body: `
      <form id="client-form" class="form-grid">
        <div class="field"><label>Name</label><input name="name" required value="${esc(existing?.name || "")}" /></div>
        <div class="field"><label>Company</label><input name="company" value="${esc(existing?.company || "")}" /></div>
        <div class="field"><label>Email</label><input name="email" type="email" value="${esc(existing?.email || "")}" /></div>
        <div class="field"><label>Phone</label><input name="phone" value="${esc(existing?.phone || "")}" /></div>
        <div class="field full"><label>Notes</label><textarea name="notes">${esc(existing?.notes || "")}</textarea></div>
        <div class="modal-actions field full">
          <button type="button" class="btn secondary" id="m-cancel">Cancel</button>
          <button class="btn" type="submit">${isEdit ? "Save" : "Add client"}</button>
        </div>
      </form>
    `,
    onMount: (modal) => {
      modal.querySelector("#m-cancel").onclick = closeModal;
      modal.querySelector("#client-form").onsubmit = async (e) => {
        e.preventDefault();
        const payload = Object.fromEntries(new FormData(e.target).entries());
        const btn = modal.querySelector("#client-form button[type=submit]");
        btn.disabled = true;
        try {
          if (isEdit) {
            const saved = await api(`/api/clients/${existing.id}`, {
              method: "PUT",
              body: JSON.stringify(payload),
            });
            if (!saved || !saved.id) {
              throw new Error("Could not save client.");
            }
            closeModal();
            toast(`Saved ${saved.name}`);
          } else {
            const saved = await api("/api/clients", { method: "POST", body: JSON.stringify(payload) });
            if (!saved || !saved.id) {
              throw new Error("Could not add client.");
            }
            closeModal();
            toast("Client added");
          }
          renderClients();
        } catch (err) {
          btn.disabled = false;
          toast(err.message || "Could not save client", "error");
        }
      };
    },
  });
}

async function renderClients() {
  const clients = await api("/api/clients");
  appEl.innerHTML = `
    ${topbar({
      eyebrow: "Collections",
      title: "Clients",
      subtitle: "Add a client, then edit them any time — email is what Send now uses.",
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
          <thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Open balance</th><th>Invoices</th><th></th></tr></thead>
          <tbody>${rows
            .map(
              (c) => `<tr>
                <td>${esc(c.name)}</td>
                <td>${esc(c.company || "—")}</td>
                <td>${esc(c.email || "—")}</td>
                <td>${esc(c.phone || "—")}</td>
                <td class="mono">${money(c.open_balance)}</td>
                <td>${c.invoice_count}</td>
                <td class="row-actions">
                  <button class="btn sm secondary" data-edit-client="${c.id}">Edit</button>
                </td>
              </tr>`
            )
            .join("")}</tbody></table>`
      : empty("No clients yet.");
    el.querySelectorAll("[data-edit-client]").forEach((btn) =>
      btn.addEventListener("click", () => {
        const client = rows.find((c) => String(c.id) === String(btn.dataset.editClient));
        if (client) openClientModal(client);
      })
    );
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
  appEl.querySelector("#btn-new-client").onclick = () => openClientModal();
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
                <button type="button" class="btn sm" data-edit-inv="${r.invoice_id}">Edit invoice</button>
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
    try {
      const res = await api("/api/reminders/send-due", { method: "POST", body: "{}" });
      toast(`Sent ${res.sent} reminder${res.sent === 1 ? "" : "s"}`);
      renderReminders();
    } catch (err) {
      toast(err.message || "Send failed", "error");
    }
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
      subtitle: "Use {{client_name}}, {{number}}, {{title}}, {{amount_due}}, {{due_date}}, {{business_name}}. The Invoice template is what Send invoice emails (a PDF is attached automatically).",
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
  const [s, billing, mail] = await Promise.all([
    api("/api/settings"),
    api("/api/billing/status"),
    api("/api/mail/status").catch(() => ({ configured: false, provider: "none" })),
  ]);
  const mailLabel = mail.configured
    ? mail.provider === "smtp"
      ? `SMTP ready (${mail.smtp_host}:${mail.smtp_port}) sending as ${mail.mail_from}`
      : mail.provider === "resend"
        ? `Resend ready, sending as ${mail.mail_from}`
        : mail.provider === "resend_then_smtp"
          ? `Resend first, Hostinger SMTP fallback (${mail.smtp_host}) as ${mail.mail_from}`
          : `Mail ready (${mail.provider})`
    : "Not configured — add SMTP or RESEND_API_KEY in .env on the server.";
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
    <div class="panel" style="margin-bottom:14px">
      <div class="panel-header"><h2>Email delivery</h2></div>
      <p class="settings-note" style="margin-bottom:14px">${mailLabel}</p>
      <div class="actions">
        <button class="btn sm" type="button" id="btn-mail-test">Send test email to me</button>
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
          Email reminders send through .env (Resend or Hostinger SMTP). SMS still requires Pro and is not wired yet.
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
      toast(err.message || "Portal unavailable", "error");
    }
  });
  appEl.querySelector("#btn-mail-test")?.addEventListener("click", async () => {
    const btn = appEl.querySelector("#btn-mail-test");
    btn.disabled = true;
    try {
      const res = await api("/api/mail/test", { method: "POST", body: "{}" });
      toast(`Test email sent to ${res.to} via ${res.provider || "mail"}`);
    } catch (err) {
      toast(err.message || "Test email failed", "error");
    } finally {
      btn.disabled = false;
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
      try {
        await api(`/api/reminders/${btn.dataset.sendReminder}/send`, { method: "POST", body: "{}" });
        toast("Reminder sent");
        route();
      } catch (err) {
        toast(err.message || "Send failed", "error");
      }
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
    toast(err.message || "Something went wrong", "error");
  }
}

window.addEventListener("hashchange", route);
appEl.addEventListener("click", (e) => {
  const editBtn = e.target.closest("[data-edit-inv]");
  if (!editBtn || !appEl.contains(editBtn)) return;
  e.preventDefault();
  openInvoiceEditor(editBtn.dataset.editInv);
});
api("/api/me").then((me) => {
  if (me.settings) {
    document.getElementById("workspace-label").textContent =
      `${me.settings.business_name} · ${me.settings.plan === "trial" ? "Trial" : me.settings.plan}`;
  }
  route();
});
