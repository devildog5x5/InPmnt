(function () {
  var amount = document.getElementById("roi-amount");
  var count = document.getElementById("roi-count");
  var out = document.getElementById("roi-result");
  var fig = document.getElementById("roi-figure");
  if (!amount || !count || !out || !fig) return;

  function money(n) {
    var digits = Math.round(n * 100) % 100 === 0 ? 0 : 2;
    return n.toLocaleString("en-US", {
      style: "currency",
      currency: "USD",
      minimumFractionDigits: digits,
      maximumFractionDigits: digits
    });
  }

  function render() {
    var invoice = Math.max(0, Number(amount.value) || 0);
    var late = Math.max(0, Math.floor(Number(count.value) || 0));
    if (invoice <= 0) {
      fig.textContent = "—";
      out.textContent = "Enter an average invoice amount.";
      return;
    }
    var book = invoice * late;
    if (late <= 0) {
      fig.textContent = money(invoice);
      out.textContent = "Add how many invoices run late in a month.";
      return;
    }
    fig.textContent = money(book);
    out.textContent = late === 1
      ? "One invoice, in the account a week sooner. InvoicePay is $4.99 this month."
      : late + " invoices, in the account a week sooner. InvoicePay is $4.99 this month.";
  }

  amount.addEventListener("input", render);
  count.addEventListener("input", render);
  render();
})();
