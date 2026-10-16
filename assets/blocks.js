/* Supported WooCommerce Checkout Blocks extension. */
(() => {
  'use strict';
  const api = window.wc?.blocksCheckout;
  if (!api?.registerCheckoutBlock || !window.wp?.element) return;
  const { createElement: h, useEffect, useState } = window.wp.element;
  function MemberBenefits() {
    const [amounts, setAmounts] = useState({});
    const [state, setState] = useState(null), [error, setError] = useState(''), [busy, setBusy] = useState(false);
    const [intent] = useState(() => [...crypto.getRandomValues(new Uint8Array(16))].map(v => v.toString(16).padStart(2, '0')).join(''));
    useEffect(() => { if (window.ghrConfig?.loggedIn) window.ghrRequest('checkout', undefined, 'GET').then(setState).catch(e => setError(e.message)); }, []);
    if (!window.ghrConfig?.loggedIn) return null;
    async function update(data) {
      setBusy(true); setError('');
      try { await api.extensionCartUpdate({namespace: 'ghr', data}); setState(await window.ghrRequest('checkout', undefined, 'GET')); }
      catch (e) { setError(e.message); } finally { setBusy(false); }
    }
    return h('section', {className: 'ghr'}, h('h3', null, 'ঘরোয়া হাট সদস্য সুবিধা'), error && h('p', {role:'alert'}, error), state && h('p', null, state.message), state && Object.entries(state.wallets).map(([wallet, data]) => h('div', {key: wallet, className:'ghr-checkout-row'}, h('span', null, wallet + ': ' + (wallet === 'seeds' ? data.available + ' বীজ' : '৳' + (data.available / 100).toFixed(2))), h('input', {type:'number', min:wallet==='seeds'?1:0.01, step:wallet==='seeds'?1:0.01, max:wallet==='seeds'?data.maximum:data.maximum/100, value:amounts[wallet] ?? (wallet==='seeds'?data.maximum:(data.maximum/100).toFixed(2)), 'aria-label':wallet==='seeds'?'বিশ্বাস বীজের পরিমাণ':'ব্যবহারযোগ্য টাকার পরিমাণ', onChange:event=>setAmounts({...amounts,[wallet]:event.target.value})}), h('button', {type:'button', disabled:busy || data.maximum<=0, onClick:()=>{const entered=String(amounts[wallet] ?? (wallet==='seeds'?data.maximum:(data.maximum/100).toFixed(2)));if(!/^\d+(\.\d{1,2})?$/.test(entered)){setError('সঠিক পরিমাণ লিখুন।');return;}const units=wallet==='seeds'?entered:String(BigInt(entered.split('.')[0])*100n+BigInt((entered.split('.')[1]||'').padEnd(2,'0')));update({wallet,units,intent});}}, 'ব্যবহার করুন'))), state?.reservation && h('button', {type:'button', disabled:busy, onClick:()=>update({remove:true})}, 'সুবিধা সরান'));
  }
  api.registerCheckoutBlock({metadata: {apiVersion: 2, name:'ghr/member-benefits', title:'Ghoroa Hat Member Benefits', category:'woocommerce', parent:['woocommerce/checkout-fields-block'], supports:{multiple:false,reusable:false}}, component:MemberBenefits});
})();
