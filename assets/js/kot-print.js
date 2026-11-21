/*
 * AK KOT auto-print (shared by the Kitchen screen and the owner Orders board).
 *
 * How it prints without anyone pressing a button:
 *   - The screen polls orders every few seconds (existing code).
 *   - For every NEW order it builds a KOT and prints it through a hidden iframe.
 *   - Chrome started with --kiosk-printing sends that straight to the default
 *     printer (any printer Windows knows: USB, network/IP, Wi-Fi, Bluetooth).
 *     Without that flag the normal print dialog opens instead.
 *
 * Duplicate guard: printed order ids are kept in localStorage (per restaurant),
 * so a refresh, or the Kitchen + Orders screens open in the same browser,
 * never print the same KOT twice.
 */
(function (w) {
  'use strict';
  var esc = function (s) {
    return (s == null ? '' : String(s)).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  function lsGet(k, d) { try { var v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } }
  function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  var KOT = {
    key: 'x', name: '', queue: [], busy: false,

    /** opts: {tenant, name, autoEl, widthEl, testEl} (elements optional). */
    init: function (opts) {
      this.key = String(opts.tenant || 'x');
      this.name = opts.name || '';
      var self = this;
      if (opts.autoEl) {
        opts.autoEl.checked = this.enabled();
        opts.autoEl.addEventListener('change', function () {
          lsSet('ak_kot_auto_' + self.key, this.checked ? '1' : '0');
          if (this.checked && w.AK && AK.toast) AK.toast('success', 'Auto-print ON: new orders will print a KOT automatically.');
        });
      }
      if (opts.widthEl) {
        opts.widthEl.value = this.width();
        opts.widthEl.addEventListener('change', function () { lsSet('ak_kot_width_' + self.key, this.value); });
      }
      if (opts.testEl) {
        opts.testEl.addEventListener('click', function () {
          self.print({ id: 0, order_no: 'TEST', table_no: '5', order_type: 'dine_in', created_at: '',
            customer_name: '', staff_name: 'Test',
            items: [{ qty: 2, item_name: 'Paneer Tikka', notes: 'Less spicy' }, { qty: 1, item_name: 'Butter Naan' }] });
        });
      }
    },
    enabled: function () { return lsGet('ak_kot_auto_' + this.key, '0') === '1'; },
    width: function () { return lsGet('ak_kot_width_' + this.key, '80'); },

    printedSet: function () {
      try { return JSON.parse(lsGet('ak_kot_printed_' + this.key, '[]')) || []; } catch (e) { return []; }
    },
    markPrinted: function (id) {
      var s = this.printedSet();
      if (s.indexOf(+id) === -1) { s.push(+id); }
      if (s.length > 400) { s = s.slice(-400); }
      lsSet('ak_kot_printed_' + this.key, JSON.stringify(s));
    },
    wasPrinted: function (id) { return this.printedSet().indexOf(+id) !== -1; },

    /**
     * Call after every poll with the full order list.
     * firstLoad: on the first poll after opening the screen, only orders placed
     * in the last 2 minutes are printed; older ones are just marked as seen.
     */
    handle: function (orders, firstLoad) {
      var self = this, on = this.enabled(), now = Date.now();
      (orders || []).slice().sort(function (a, b) { return +a.id - +b.id; }).forEach(function (o) {
        if (!o || !o.id || self.wasPrinted(o.id)) return;
        if (o.status === 'cancelled' || o.status === 'completed') { self.markPrinted(o.id); return; }
        var age = (now - new Date(String(o.created_at || '').replace(' ', 'T')).getTime()) / 60000;
        if (!on || (firstLoad && !(age >= 0 && age < 2))) { self.markPrinted(o.id); return; }
        self.markPrinted(o.id);
        self.print(o);
      });
    },

    html: function (o) {
      var mm = this.width() === '58' ? 58 : 80;
      var rows = (o.items || []).map(function (i) {
        var ad = '';
        try { (JSON.parse(i.addons_json || '[]') || []).forEach(function (a) { ad += '<div class="ad">+ ' + esc(a.name) + '</div>'; }); } catch (e) {}
        return '<tr><td class="q">' + esc(i.qty) + '</td><td>' + esc(i.item_name) +
          (i.variant_label ? ' (' + esc(i.variant_label) + ')' : '') + ad +
          (i.notes ? '<div class="nt">* ' + esc(i.notes) + '</div>' : '') + '</td></tr>';
      }).join('');
      var where = o.table_no ? 'TABLE ' + esc(o.table_no) : esc(String(o.order_type || '').replace('_', ' ').toUpperCase());
      var d = new Date();
      var when = d.toLocaleDateString('en-IN') + ' ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
      return '<!doctype html><html><head><meta charset="utf-8"><title>KOT</title><style>' +
        '@page{size:' + mm + 'mm auto;margin:0}*{box-sizing:border-box}' +
        'body{font-family:Arial,Helvetica,sans-serif;width:' + (mm - 4) + 'mm;margin:0 auto;padding:3mm 1mm;color:#000}' +
        '.c{text-align:center}.big{font-size:' + (mm === 58 ? 20 : 24) + 'px;font-weight:800;margin:2px 0}' +
        '.line{border-top:1px dashed #000;margin:5px 0}table{width:100%;border-collapse:collapse}' +
        'td{vertical-align:top;padding:3px 0;font-size:' + (mm === 58 ? 14 : 16) + 'px;font-weight:600}' +
        'td.q{width:28px;font-size:' + (mm === 58 ? 16 : 18) + 'px;font-weight:800}' +
        '.nt{font-weight:800;font-size:13px}.ad{font-weight:400;font-size:12px}.sm{font-size:12px}' +
        '</style></head><body>' +
        '<div class="c sm"><b>*** KOT ***</b></div>' +
        (this.name ? '<div class="c sm">' + esc(this.name) + '</div>' : '') +
        '<div class="c big">' + where + '</div>' +
        '<div class="c sm">#' + esc(o.order_no) + ' &middot; ' + esc(when) + '</div>' +
        (o.staff_name ? '<div class="c sm">By: ' + esc(o.staff_name) + '</div>' : '') +
        (o.customer_name ? '<div class="c sm">' + esc(o.customer_name) + '</div>' : '') +
        '<div class="line"></div><table>' + rows + '</table><div class="line"></div>' +
        '<div class="c sm">Items: ' + (o.items || []).reduce(function (s, i) { return s + (+i.qty || 0); }, 0) + '</div>' +
        '</body></html>';
    },

    /** Print one KOT now (queued so several new orders print one after another). */
    print: function (o) { this.queue.push(o); this.next(); },
    next: function () {
      if (this.busy || !this.queue.length) return;
      this.busy = true;
      var self = this, o = this.queue.shift();
      var f = document.createElement('iframe');
      f.setAttribute('aria-hidden', 'true');
      f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
      document.body.appendChild(f);
      var doc = f.contentWindow.document;
      doc.open(); doc.write(this.html(o)); doc.close();
      setTimeout(function () {
        try { f.contentWindow.focus(); f.contentWindow.print(); } catch (e) {}
        setTimeout(function () { if (f.parentNode) f.parentNode.removeChild(f); self.busy = false; self.next(); }, 1500);
      }, 300);
    }
  };
  w.AKKot = KOT;
})(window);
