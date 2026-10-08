(function () {
  var amount = document.getElementById("roi-amount");
  var count = document.getElementById("roi-count");
  var out = document.getElementById("roi-result");
  if (!amount || !count || !out) return;

  var PRICE = 4.99;

  function money(n) {
    return n.toLocaleString("en-US", { style: "currency", currency: "USD" });
  }

  function render() {
    var invoice = Math.max(0, Number(amount.value) || 0);
    var late = Math.max(0, Math.floor(Number(count.value) || 0));
    var book = invoice * late;
    if (invoice <= 0) {
      out.textContent = "Enter an average invoice amount to compare it with $4.99.";
      return;
    }
    var months = invoice / PRICE;
    var monthLabel = months >= 10 ? String(Math.round(months)) : months.toFixed(1);
    var bookLine;
    if (late <= 0) {
      bookLine = "Add how many invoices usually run late in a month. ";
    } else if (late === 1) {
      bookLine = "If that " + money(invoice) + " invoice is paid a week sooner, " + money(book) + " is in the account a week earlier. ";
    } else {
      bookLine = "If those " + late + " late invoices (" + money(book) + ") are paid a week sooner, that cash is in the account a week earlier. ";
    }
    out.textContent = bookLine
      + "InvoicePay is $4.99 for the month. One invoice of " + money(invoice) + " covers about " + monthLabel
      + " months of the subscription. This is arithmetic, not a promise that every reminder gets paid.";
  }

  amount.addEventListener("input", render);
  count.addEventListener("input", render);
  render();
})();
