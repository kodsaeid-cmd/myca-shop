'use strict';
(() => {
 const API='/api/platform.php?action=';
 let csrf='', session={logged_in:false,features:{auth:false,checkout:false}},accountTab='overview';
 const $=(s,root=document)=>root.querySelector(s);
 const esc=s=>String(s??'').replace(/[&<>"']/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[x]));
 const money=x=>new Intl.NumberFormat('fa-IR').format(Number(x)||0)+' تومان';
 const label=s=>({pending:'در انتظار',confirmed:'تأیید شده',cancelled:'لغوشده',closed:'بسته‌شده',not_started:'پرداخت نشده',paid:'پرداخت موفق',failed:'پرداخت ناموفق',unfulfilled:'آماده‌سازی نشده',picking:'جمع‌آوری کالا',packed:'بسته‌بندی شده',shipped:'ارسال شده',delivered:'تحویل شده',delivery_failed:'تحویل ناموفق',returned:'مرجوع شده',requested:'ثبت درخواست',reviewing:'در حال بررسی',approved:'تأیید شده',rejected:'رد شده',received:'دریافت شده'})[s]||s;
 async function api(action,body){let opt={method:body===undefined?'GET':'POST',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}};if(body!==undefined){opt.headers['X-CSRF-Token']=csrf;opt.headers['Content-Type']='application/json';opt.body=JSON.stringify(body)}let r=await fetch(API+encodeURIComponent(action.split('&')[0])+(action.includes('&')?'&'+action.slice(action.indexOf('&')+1):''),opt);let d=await r.json().catch(()=>({ok:false,error:'پاسخ سرور معتبر نیست.'}));if(!r.ok||!d.ok)throw Error(d.error||'خطای ارتباط');return d}
 function message(text,error=false,root=document.body){const box=document.createElement('div');box.className='api-msg'+(error?' error':'');box.textContent=text;root.appendChild(box);setTimeout(()=>box.remove(),6000)}
 async function boot(){try{let d=await api('session');csrf=d.csrf;session=d;}catch(e){message(e.message,true)}const page=$('[data-page]')?.dataset.page;if(page==='cart')await drawCart();if(page==='account')await drawAccount();if(page==='checkout')await drawCheckout();}
 async function drawCart(){let box=$('#cart-panel');if(!box)return;try{let d=await api('cart');let c=d.cart;if(!c.items.length){box.innerHTML='<p>سبد شما خالی است. <a class="muted-link" href="/">مشاهده کالاها</a></p>';$('#cart-total').innerHTML='<h2>سبد خرید</h2><p>هنوز کالایی انتخاب نشده است.</p>';return;}
 box.innerHTML=c.items.map(x=>`<div class="cart-line"><div><b>${esc(x.name)}</b><p class="small muted">${esc(x.variant_title||'')} ${!x.purchasable?' · موجودی تغییر کرده است':''}</p></div><strong>${money(x.price)}</strong><label>تعداد <input class="full-input cart-qty" type="number" min="0" max="20" data-id="${x.id}" value="${x.quantity}"></label><button class="light-btn cart-update" data-id="${x.id}">ثبت تغییر</button></div>`).join('');$('#cart-total').innerHTML=`<h2>خلاصه سبد</h2><h3>${money(c.subtotal)}</h3><p class="muted">هزینه نهایی پس از اعتبارسنجی مجدد تعیین می‌شود.</p><a class="primary-btn" href="/checkout/">ادامه فرآیند خرید</a>`;
 }catch(e){box.innerHTML='<div class="api-msg error">'+esc(e.message)+'</div>'}}
 function authForm(){const disabled=!session.features?.auth;return `<div class="panel"><h2>ورود یا ثبت‌نام مشتری</h2><p class="notice">${disabled?'ورود تا فعال‌شدن HTTPS و تنظیمات امنیتی غیرفعال است.':'برای ورود از ایمیل و رمز عبور آزمایشی استفاده کنید؛ شماره موبایل/OTP در مرحله اتصال سرویس پیامک افزوده می‌شود.'}</p><form id="customer-login"><label>ایمیل <input class="full-input" name="email" type="email" autocomplete="email" required></label><label>رمز عبور (حداقل ۱۲ کاراکتر) <input class="full-input" name="password" type="password" autocomplete="current-password" minlength="12" required></label><button class="primary-btn" ${disabled?'disabled':''}>ورود</button></form><hr><form id="customer-register"><h3>ساخت حساب آزمایشی</h3><label>نام و نام خانوادگی <input class="full-input" name="full_name" required></label><label>ایمیل <input class="full-input" name="email" type="email" required></label><label>رمز عبور <input class="full-input" name="password" type="password" minlength="12" required></label><button class="light-btn" ${disabled?'disabled':''}>ثبت نام</button></form></div>`}
 async function drawAccount(){let root=$('#account-panel');if(!root)return;if(!session.logged_in){root.outerHTML='<div id="account-panel">'+authForm()+'</div>';return;}
 try{const me=(await api('me')).customer;
 if(accountTab==='overview')root.innerHTML=`<h2>سلام ${esc(me.full_name)}</h2><p>به پنل شخصی فروشگاه مایکا خوش آمدید.</p><p class="muted">از منوی کنار می‌توانید سفارش‌ها، آدرس‌ها و پشتیبانی را مدیریت کنید.</p><button class="light-btn account-logout">خروج از حساب</button>`;
 if(accountTab==='profile')root.innerHTML=`<h2>پروفایل من</h2><form id="profile-form"><label>نام و نام خانوادگی<input class="full-input" name="full_name" value="${esc(me.full_name)}" required></label><button class="primary-btn">ذخیره نام</button></form><p>ایمیل: ${esc(me.email)}</p><p>شماره موبایل: ${esc(me.mobile||'تأیید نشده')}</p><p class="notice">تغییر شماره موبایل پس از اتصال سرویس احراز هویت فعال می‌شود.</p>`;
 if(accountTab==='addresses'){
  let d=await api('addresses');root.innerHTML='<h2>آدرس‌های من</h2>'+d.addresses.map(x=>`<div class="review"><b>${esc(x.label||'نشانی')}</b><p>${esc(x.recipient)} — ${esc(x.province)}، ${esc(x.city)}، ${esc(x.street)}</p><button class="light-btn address-default" data-id="${x.id}">آدرس پیش‌فرض</button> <button class="light-btn address-delete" data-id="${x.id}">حذف نشانی</button></div>`).join('')+`<form id="address-form" class="form-2"><label>عنوان<input name="label" class="full-input" placeholder="خانه"></label><label>گیرنده<input name="recipient" class="full-input" required></label><label>شماره تماس<input name="phone" class="full-input" required></label><label>استان<input name="province" class="full-input" required></label><label>شهر<input name="city" class="full-input" required></label><label>کدپستی<input name="postal_code" class="full-input" required></label><label class="wide">نشانی کامل<input name="street" class="full-input" required></label><label>پلاک/واحد<input name="building" class="full-input"></label><div class="wide"><button class="primary-btn">ذخیره آدرس</button></div></form>`;
 }
 if(accountTab==='orders'){
  let d=await api('orders');root.innerHTML='<h2>سفارش‌های من</h2><div class="table-wrap"><table class="clean-table"><thead><tr><th>شماره</th><th>مبلغ</th><th>پرداخت</th><th>عملیات</th><th>ارسال</th></tr></thead><tbody>'+d.orders.map(o=>`<tr><td><button class="light-btn order-detail" data-no="${esc(o.order_number)}">${esc(o.order_number)}</button></td><td>${money(o.grand_total)}</td><td>${label(o.payment_status)}</td><td>${label(o.order_status)}</td><td>${label(o.fulfillment_status)}</td></tr>`).join('')+'</tbody></table></div>';
 }
 if(accountTab==='wishlist'){
  let d=await api('wishlist');root.innerHTML='<h2>علاقه‌مندی‌های من</h2>'+d.items.map(p=>`<div class="review"><a href="/product/${encodeURIComponent(p.slug)}/">${esc(p.name)}</a> — ${money(p.price)}</div>`).join('')||'<p>هنوز محصولی ذخیره نکرده‌اید.</p>';
 }
 if(accountTab==='reviews'){const d=await api('my.reviews');root.innerHTML='<h2>نظرات من</h2>'+d.reviews.map(r=>`<div class="review"><a href="/product/${encodeURIComponent(r.slug)}/"><b>${esc(r.name)}</b></a><p>${esc(r.body)}</p><small>${r.rating} از ۵ · ${label(r.status)}</small></div>`).join('');if(!d.reviews.length)root.innerHTML+='<p>هنوز نظری ثبت نکرده‌اید.</p>' ;}
 if(accountTab==='returns'){
  const d=await api('returns');root.innerHTML='<h2>مرجوعی‌ها</h2>'+d.returns.map(r=>`<div class="review">سفارش ${esc(r.order_id)} — ${label(r.status)}</div>`).join('')+'<p class="muted small">درخواست مرجوعی با انتخاب شماره سفارش در مرحله خدمات پس از فروش تکمیل می‌شود.</p>';
 }
 if(accountTab==='tickets'){
  const d=await api('tickets');root.innerHTML='<h2>درخواست‌های پشتیبانی</h2>'+d.tickets.map(t=>`<div class="review"><b>${esc(t.subject)}</b><p>${esc(t.body)}</p><p>${esc(t.admin_reply||label(t.status))}</p></div>`).join('')+`<form id="ticket-form"><input name="subject" class="full-input" placeholder="موضوع درخواست" required><textarea name="body" class="full-input" placeholder="شرح مشکل" required></textarea><button class="primary-btn">ثبت درخواست</button></form>`;
 }
 if(accountTab==='consent')root.innerHTML='<h2>اطلاع‌رسانی و حریم خصوصی</h2><form id="consent-form"><label><input name="email" type="checkbox"> دریافت پیشنهادهای بازاریابی با ایمیل</label><p><label><input name="sms" type="checkbox"> دریافت پیشنهادهای بازاریابی با پیامک</label></p><p class="muted">اعلان‌های ضروری سفارش از پیام‌های اختیاری تبلیغاتی جدا هستند.</p><button class="primary-btn">ذخیره رضایت</button></form>';
 }catch(e){root.innerHTML='<div class="api-msg error">'+esc(e.message)+'</div>'}}
 async function drawCheckout(){let root=$('#checkout-panel');if(!root)return;if(!session.features?.auth){root.innerHTML='<div class="notice">تسویه‌حساب تا برقراری HTTPS و فعال‌سازی ورود مشتری غیرفعال است.</div>';return;}if(!session.logged_in){root.innerHTML=authForm();return;}
 try{let cart=(await api('cart')).cart;let addresses=(await api('addresses')).addresses;$('#checkout-total').innerHTML='<h2>جمع کالاها</h2><h3>'+money(cart.subtotal)+'</h3><p class="notice">سفارش آزمایشی است؛ پرداخت و ارسال انجام نمی‌شود.</p>';
 root.innerHTML=`<h2>انتخاب آدرس گیرنده</h2><form id="checkout-form"><select class="full-input" name="address_id" required>${addresses.map(a=>`<option value="${a.id}">${esc(a.label)} — ${esc(a.city)}، ${esc(a.street)}</option>`).join('')}</select><p class="muted">برای افزودن نشانی به <a class="muted-link" href="/account/">حساب کاربری</a> بروید.</p><button class="primary-btn" ${(!addresses.length||!session.features?.checkout)?'disabled':''}>${session.features?.checkout?'ثبت سفارش آزمایشی (بدون پرداخت)':'ثبت سفارش هنوز فعال نیست'}</button></form>`;
 }catch(e){root.textContent=e.message;}}
 document.addEventListener('click',async e=>{
  const t=e.target.closest('button');if(!t)return;
  try{
   if(t.classList.contains('add-to-cart')){const variant=$('#variant')?.value||null;await api('cart.add',{product_id:t.dataset.product,variant_id:variant,quantity:1});message('کالا به سبد سروری اضافه شد.');}
   if(t.classList.contains('cart-update')){const inp=$('.cart-qty[data-id="'+t.dataset.id+'"]');await api('cart.update',{item_id:Number(t.dataset.id),quantity:Number(inp.value)});await drawCart();}
   if(t.dataset.accountTab){accountTab=t.dataset.accountTab;document.querySelectorAll('[data-account-tab]').forEach(x=>x.classList.toggle('active',x===t));await drawAccount();}
   if(t.classList.contains('account-logout')){await api('logout',{});session.logged_in=false;await drawAccount();}
   if(t.classList.contains('address-delete')){if(confirm('این نشانی حذف شود؟')){await api('address.delete',{id:Number(t.dataset.id)});await drawAccount();}}
   if(t.classList.contains('address-default')){await api('address.default',{id:Number(t.dataset.id)});await drawAccount();}
   if(t.classList.contains('save-wishlist')){let d=await api('wishlist.toggle',{product_id:t.dataset.product});message(d.saved?'به علاقه‌مندی‌ها اضافه شد.':'از علاقه‌مندی‌ها حذف شد.');}
   if(t.classList.contains('submit-review')){const r=t.closest('.review-form');await api('review.submit',{product_id:r.dataset.product,rating:Number($('[name=rating]',r).value),title:$('[name=title]',r).value,body:$('[name=body]',r).value});message('نظر دریافت شد و منتظر بررسی است.');}
   if(t.classList.contains('submit-question')){const r=t.closest('.question-form');await api('question.submit',{product_id:r.dataset.product,question:$('textarea',r).value});message('سؤال دریافت شد و منتظر بررسی است.');}
   if(t.classList.contains('order-detail')){let d=await api('order.detail&number='+encodeURIComponent(t.dataset.no));let root=$('#account-panel');root.innerHTML=`<button class="light-btn" onclick="location.reload()">بازگشت</button><h2>سفارش ${esc(d.order.order_number)}</h2><p>پرداخت: ${label(d.order.payment_status)}، ارسال: ${label(d.order.fulfillment_status)}</p>`+d.items.map(i=>`<div class="review">${esc(i.name)} — ${i.quantity} عدد — ${money(i.line_total)}</div>`).join('')+'<h3>تاریخچه وضعیت</h3>'+d.events.map(v=>`<p>${esc(v.created_at)} · ${label(v.to_status)}</p>`).join('');}
  }catch(err){message(err.message,true)}
 });
 document.addEventListener('submit',async e=>{
  const f=e.target;if(!['customer-login','customer-register','profile-form','address-form','ticket-form','consent-form','checkout-form'].includes(f.id))return;e.preventDefault();const data=Object.fromEntries(new FormData(f).entries());
  try{if(f.id==='customer-login'||f.id==='customer-register'){let result=await api(f.id==='customer-login'?'login':'register',data);csrf=result.csrf;session.logged_in=true;accountTab='overview';await drawAccount();if($('[data-page="checkout"]'))await drawCheckout();}
   if(f.id==='profile-form'){await api('profile.save',data);await drawAccount();}
   if(f.id==='address-form'){await api('address.save',data);await drawAccount();}
   if(f.id==='ticket-form'){await api('ticket.create',data);await drawAccount();}
   if(f.id==='consent-form'){await api('consent.save',{email:!!data.email,sms:!!data.sms});message('تنظیمات ذخیره شد.');}
   if(f.id==='checkout-form'){let result=await api('checkout.create',{address_id:Number(data.address_id)});$('#checkout-panel').innerHTML='<h2>سفارش آزمایشی ثبت شد</h2><p>شماره سفارش: '+esc(result.order_number)+'</p><p class="notice">این سفارش پرداخت نشده و برای فروش واقعی معتبر نیست.</p>';}
  }catch(err){message(err.message,true,f)}
 });
 boot();
})();
