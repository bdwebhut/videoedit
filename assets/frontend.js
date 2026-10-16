/* Ghoroa Hat: isolated components, no jQuery and no client-controlled financial calculations. */
(() => {
  'use strict';
  const config = window.ghrConfig;
  if (!config) return;
  async function request(path, data, method = 'POST') {
    const response = await fetch(config.api + path, { method, credentials: 'same-origin', headers: { 'X-WP-Nonce': config.nonce, 'Content-Type': 'application/json' }, ...(data === undefined ? {} : {body: JSON.stringify(data)}) });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || config.error);
    return result;
  }
  window.ghrRequest = request;
  function status(element, message, error = false) {
    const region = element.closest('.ghr')?.querySelector('.ghr-status');
    if (region) { region.textContent = message; region.setAttribute('role', error ? 'alert' : 'status'); }
  }
  document.addEventListener('click', async event => {
    const copy = event.target.closest('[data-ghr-copy]');
    if (copy) { try { await navigator.clipboard.writeText(copy.dataset.ghrCopy); status(copy, 'লিংক কপি হয়েছে।'); } catch (_) { status(copy, 'উপরের লিংক নির্বাচন করে কপি করুন।', true); } return; }
    const button = event.target.closest('[data-ghr-action]');
    if (!button) return;
    button.disabled = true; status(button, 'অপেক্ষা করুন…');
    try {
      const result = await request(button.dataset.ghrAction, button.dataset.purpose ? {purpose: button.dataset.purpose} : {});
      if (result.reward_name) { status(button, result.reward_name); button.closest('.ghr-box')?.classList.add('ghr-revealed'); }
      else status(button, 'সম্পন্ন হয়েছে।');
      if (!button.dataset.ghrAction.startsWith('verification/')) setTimeout(() => location.reload(), 900);
    } catch (error) { status(button, error.message, true); } finally { button.disabled = false; }
  });
  document.addEventListener('submit', async event => {
    const form = event.target.closest('[data-ghr-form]'); if (!form) return;
    event.preventDefault(); const data = Object.fromEntries(new FormData(form));
    form.querySelectorAll('input[type=checkbox]').forEach(input => { data[input.name] = input.checked; });
    const submit = form.querySelector('button'); if (submit) submit.disabled = true;
    status(form, 'অপেক্ষা করুন…');
    try { const result = await request(form.dataset.ghrForm, data); if (form.dataset.ghrForm.startsWith('admin/')) { let output=form.querySelector('pre'); if (!output) {output=document.createElement('pre');form.append(output);} output.textContent=JSON.stringify(result,null,2); } status(form, 'সম্পন্ন হয়েছে।' + (result.snapshot_id ? ' Snapshot: ' + result.snapshot_id : '') + (result.definition_id ? ' Definition: ' + result.definition_id : '') + (result.campaign_id ? ' Campaign: ' + result.campaign_id : '')); if (!form.dataset.ghrForm.startsWith('admin/') && !form.dataset.ghrForm.startsWith('verification/request')) setTimeout(() => location.reload(), 700); }
    catch (error) { status(form, error.message, true); } finally { if (submit) submit.disabled = false; }
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') { document.querySelectorAll('.ghr details[open]').forEach(details => { details.open = false; details.querySelector('summary')?.focus(); }); } });
  document.addEventListener('toggle', async event => {
    const details = event.target; if (!details.open || !details.matches?.('[data-ghr-wallet]')) return;
    try { const all = await request('me/wallets', undefined, 'GET'); const wallet = details.dataset.ghrWallet; const data = all[wallet]; details.querySelector('summary').textContent = wallet + ' · ' + (wallet === 'seeds' ? data.available + ' বীজ' : '৳' + (data.available / 100).toFixed(2)); } catch (error) { status(details, error.message, true); }
  }, true);
  function checkout(node) {
    if (node.dataset.ready) return; node.dataset.ready = '1';
    const intent = [...crypto.getRandomValues(new Uint8Array(16))].map(v => v.toString(16).padStart(2, '0')).join('');
    async function load() {
      try {
        const state = await request('checkout', undefined, 'GET'); node.replaceChildren();
        const message = document.createElement('p'); message.textContent = state.message; node.append(message);
        Object.entries(state.wallets).forEach(([wallet, data]) => {
          const row = document.createElement('div'); row.className = 'ghr-checkout-row';
          const label = document.createElement('span'); label.textContent = wallet + ': ' + (wallet === 'seeds' ? data.available + ' বীজ' : '৳' + (data.available / 100).toFixed(2)); row.append(label);
          const input = document.createElement('input'); input.type = 'number'; input.min = wallet === 'seeds' ? '1' : '0.01'; input.step = wallet === 'seeds' ? '1' : '0.01'; input.max = wallet === 'seeds' ? String(data.maximum) : (data.maximum / 100).toFixed(2); input.value = input.max; input.setAttribute('aria-label', wallet === 'seeds' ? 'বিশ্বাস বীজের পরিমাণ' : 'ব্যবহারযোগ্য টাকার পরিমাণ'); row.append(input);
          const apply = document.createElement('button'); apply.type = 'button'; apply.textContent = data.maximum > 0 ? 'ব্যবহার করুন' : 'এখন ব্যবহারযোগ্য নয়'; apply.disabled = data.maximum <= 0;
          apply.onclick = async () => { apply.disabled = true; try { await request('checkout/reservations', {wallet, units: wallet === 'seeds' ? input.value : String(BigInt(input.value.split('.')[0] || '0') * 100n + BigInt((input.value.split('.')[1] || '').padEnd(2, '0').slice(0, 2))), intent}); location.reload(); } catch (e) { status(node, e.message, true); apply.disabled = false; } }; row.append(apply); node.append(row);
        });
        if (state.reservation) { const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'ওয়ালেট সুবিধা সরান'; remove.onclick = async () => { try { await request('checkout/reservations', {remove: true}); location.reload(); } catch (e) { status(node, e.message, true); } }; node.append(remove); }
      } catch (error) { node.textContent = error.message; }
    }
    load();
  }
  function scan() { if (config.loggedIn) document.querySelectorAll('[data-ghr-checkout]').forEach(checkout); }
  scan(); new MutationObserver(scan).observe(document.body, {subtree: true, childList: true});
})();
