'use strict';
(()=>{
 const root=document.getElementById('admin-panel');let csrf='',tab='dashboard',catalog=[],blocks=[],pageSlug='home';
 const esc=s=>String(s??'').replace(/[&<>"']/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[x]));
 const money=x=>new Intl.NumberFormat('fa-IR').format(Number(x)||0)+' تومان';
 const label=s=>({pending:'در انتظار',approved:'تأییدشده',rejected:'ردشده',picking:'جمع‌آوری',packed:'بسته‌بندی',shipped:'ارسال‌شده',delivered:'تحویل‌شده',unfulfilled:'ارسال نشده',paid:'پرداخت موفق',not_started:'پرداخت‌نشده',resolved:'بسته‌شده',open:'باز',reviewing:'بررسی'}[s]||s||'');
 function msg(s,error=false){const p=document.createElement('p');p.className='api-msg'+(error?' error':'');p.textContent=s;root.prepend(p);setTimeout(()=>p.remove(),6000)}
 async function request(action,body){const method=body===undefined?'GET':'POST';let r=await fetch('/api/platform-admin.php?action='+encodeURIComponent(action.split('&')[0])+(action.includes('&')?'&'+action.slice(action.indexOf('&')+1):''),{method,credentials:'same-origin',headers:{Accept:'application/json',...(method==='POST'?{'X-CSRF-Token':csrf,'Content-Type':'application/json'}:{})},...(body===undefined?{}:{body:JSON.stringify(body)})});let data=await r.json().catch(()=>({error:'پاسخ سرور معتبر نیست.'}));if(!r.ok||!data.ok)throw Error(data.error||'خطای ارتباط');return data}
 async function baseSession(){const r=await fetch('/api/admin.php?action=session',{credentials:'same-origin',cache:'no-store'});const data=await r.json();if(!r.ok||!data.ok)throw Error(data.error||'پاسخ نشست مدیریت معتبر نیست.');csrf=data.csrf;return data;}
 async function getCatalog(){const r=await fetch('/api/catalog.php');const d=await r.json();catalog=d.products||[];}
 function formText(labelText,name,value='',kind='text'){return `<label>${esc(labelText)}<${kind==='textarea'?'textarea':'input'} class="admin-input" name="${name}" ${kind==='textarea'?'':`type="${kind}" value="${esc(value)}"`}>${kind==='textarea'?esc(value)+'</textarea>':''}</label>`;}
 function rows(items,cols,render){if(!items?.length)return '<p class="placeholder">موردی ثبت نشده است.</p>';return '<div class="table-wrap"><table class="clean-table"><thead><tr>'+cols.map(c=>`<th>${esc(c)}</th>`).join('')+'</tr></thead><tbody>'+items.map(render).join('')+'</tbody></table></div>'}
 async function switchTab(next){tab=next;document.querySelectorAll('[data-tab]').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));root.innerHTML='<p class="placeholder">در حال دریافت اطلاعات...</p>';
 try{
 if(tab==='products'||tab==='categories'){await drawCatalog();return;}
 if(tab==='dashboard'){const d=await request('dashboard');root.innerHTML='<div class="admin-top"><h1>داشبورد فروشگاه</h1><button type="button" class="light-btn" data-tab="products">مدیریت محصولات</button></div><div class="stat-cards">'+Object.entries({products:'کالاها',customers:'مشتریان',orders:'سفارش‌ها',pending_orders:'سفارش‌های باز',reviews_pending:'نظرات در انتظار',abandoned_carts:'سبد رهاشده',sandbox_order_total:'مجموع سفارش‌های تست'}).map(([key,title])=>`<div class="stat-card"><small>${title}</small><strong>${key==='sandbox_order_total'?money(d.stats[key]):esc(d.stats[key])}</strong></div>`).join('')+'</div><p class="notice">اعداد سفارش و درآمد آزمایشی، فروش واقعی نیستند و نباید در گزارش مالی استفاده شوند.</p>';}
 if(tab==='orders'){const d=await request('orders');root.innerHTML='<h1>سفارش‌ها و وضعیت تحویل</h1><p class="admin-help">پرداخت توسط اپراتور به‌صورت دستی «موفق» نمی‌شود؛ تأیید آن باید در آینده از درگاه بیاید.</p>'+rows(d.items,['شماره','ایمیل','مبلغ','پرداخت','ارسال','عملیات'],o=>`<tr><td>${esc(o.order_number)}</td><td>${esc(o.email)}</td><td>${money(o.grand_total)}</td><td>${label(o.payment_status)}</td><td>${label(o.fulfillment_status)}</td><td><select class="admin-select fulfillment-select" data-id="${o.id}">${['unfulfilled','picking','packed','shipped','delivered','delivery_failed','returned'].map(s=>`<option value="${s}" ${s===o.fulfillment_status?'selected':''}>${label(s)}</option>`).join('')}</select><button data-act="fulfillment" data-id="${o.id}" class="light-btn">ثبت وضعیت</button></td></tr>`);}
 if(tab==='inventory'){const d=await request('inventory');root.innerHTML='<h1>موجودی و دفتر تغییرات</h1><p class="admin-help">تغییر موجودی با قفل رکورد انجام و در دفتر حرکات ثبت می‌شود. این جایگزین رزرو واقعی سفارش درگاه پرداخت نیست.</p>'+rows(d.products,['کالا','SKU','موجودی','اصلاح موجودی'],p=>`<tr><td>${esc(p.name)}</td><td>${esc(p.sku)}</td><td>${p.stock}</td><td><input class="admin-input inventory-delta" style="max-width:105px" type="number" placeholder="+5 یا -2" data-id="${esc(p.id)}"><input class="admin-input inventory-reason" style="max-width:200px" placeholder="دلیل تغییر" data-id="${esc(p.id)}"><button class="light-btn" data-act="inventory" data-id="${esc(p.id)}">ثبت</button></td></tr>`)+'<h3>آخرین حرکات ثبت‌شده</h3>'+rows(d.movements,['کالا','تغییر','دلیل','مدیر','زمان'],m=>`<tr><td>${esc(m.product_id)}</td><td>${m.delta}</td><td>${esc(m.reason)}</td><td>${esc(m.actor)}</td><td>${esc(m.created_at)}</td></tr>`);}
 if(tab==='customers'){const d=await request('customers');root.innerHTML='<h1>مشتریان</h1><p class="admin-help">داده‌ها فقط در نشست مدیریتی امن و پس از صدور مجوز قابل مشاهده‌اند.</p>'+rows(d.items,['شناسه','نام','ایمیل','تاریخ عضویت'],o=>`<tr><td>${o.id}</td><td>${esc(o.full_name)}</td><td>${esc(o.email)}</td><td>${esc(o.created_at)}</td></tr>`);}
 if(tab==='abandoned'){const d=await request('abandoned');root.innerHTML='<h1>سبدهای رهاشده</h1><p class="admin-help">فقط تحلیل؛ ارسال پیامک یا ایمیل خودکار فعلاً فعال نیست.</p>'+rows(d.items,['شناسه سبد','زمان آخرین فعالیت','تعداد ردیف','ارزش برآوردی'],o=>`<tr><td><code>${esc(o.id.slice(0,12))}…</code></td><td>${esc(o.updated_at)}</td><td>${o.items}</td><td>${money(o.estimated_total)}</td></tr>`);}
 if(tab==='reviews'){const d=await request('reviews');root.innerHTML='<h1>مدیریت نظرات</h1>'+rows(d.items,['محصول','نظر','امتیاز','وضعیت','عملیات'],o=>`<tr><td>${esc(o.product_name)}</td><td>${esc(o.body)}</td><td>${o.rating}</td><td>${label(o.status)}</td><td><button class="light-btn" data-act="moderate" data-id="${o.id}" data-status="approved">تأیید</button><button class="light-btn" data-act="moderate" data-id="${o.id}" data-status="rejected">رد</button></td></tr>`);}
 if(tab==='questions'){const d=await request('questions');root.innerHTML='<h1>پرسش و پاسخ محصولات</h1>'+rows(d.items,['محصول','سؤال','پاسخ','وضعیت','عملیات'],o=>`<tr><td>${esc(o.product_name)}</td><td>${esc(o.question)}</td><td><textarea class="admin-input q-answer" data-id="${o.id}">${esc(o.answer)}</textarea></td><td>${label(o.status)}</td><td><button class="light-btn" data-act="question" data-id="${o.id}">تأیید و انتشار</button></td></tr>`);}
 if(tab==='returns'){const d=await request('returns');root.innerHTML='<h1>مرجوعی و بازپرداخت</h1><p class="notice">تغییر وضعیت مرجوعی به معنی انجام بازپرداخت واقعی نیست.</p>'+rows(d.items,['سفارش','علت','وضعیت','عملیات'],o=>`<tr><td>${esc(o.order_number)}</td><td>${esc(o.reason)}</td><td>${label(o.status)}</td><td><select class="admin-select return-select" data-id="${o.id}">${['requested','reviewing','approved','rejected','received','closed'].map(s=>`<option value="${s}" ${s===o.status?'selected':''}>${label(s)}</option>`).join('')}</select><button class="light-btn" data-act="return" data-id="${o.id}">ثبت</button></td></tr>`);}
 if(tab==='tickets'){const d=await request('tickets');root.innerHTML='<h1>پشتیبانی مشتری</h1>'+rows(d.items,['مشتری','موضوع','توضیح','پاسخ','عملیات'],o=>`<tr><td>${esc(o.email)}</td><td>${esc(o.subject)}</td><td>${esc(o.body)}</td><td><textarea class="admin-input ticket-reply" data-id="${o.id}">${esc(o.admin_reply||'')}</textarea></td><td><button class="light-btn" data-act="reply" data-id="${o.id}">ثبت پاسخ</button></td></tr>`);}
 if(tab==='coupons'){const d=await request('coupons');root.innerHTML='<h1>کوپن و تخفیف</h1><p class="notice">قوانین کوپن در دیتابیس قابل تعریف هستند؛ اعمال کوپن به سفارش نهایی هنوز در مرحله تسویه فعال نشده است.</p><form id="coupon-form" class="admin-form">'+formText('کد انگلیسی','code')+`<label>نوع تخفیف<select name="type" class="admin-select"><option value="percent">درصدی</option><option value="fixed">مبلغ ثابت</option></select></label>`+formText('مقدار','value',10,'number')+formText('حداقل سبد','min_total',0,'number')+formText('سقف استفاده','usage_limit',0,'number')+'<label><input type="checkbox" name="active"> فعال</label><p><button class="primary-btn">ذخیره کوپن</button></p></form>'+rows(d.items,['کد','نوع','مقدار','فعال'],o=>`<tr><td>${esc(o.code)}</td><td>${esc(o.discount_type)}</td><td>${o.discount_value}</td><td>${o.active?'بله':'خیر'}</td></tr>`);}
 if(tab==='pages')await drawPages();
 if(['seo','media','variants'].includes(tab))await drawProductEditor();
 }catch(e){root.innerHTML='<div class="api-msg error">'+esc(e.message)+'</div>'}}
 async function drawProductEditor(){await getCatalog();root.innerHTML=`<h1>${({seo:'سئو و محتوای محصول',media:'گالری و ویدیوی محصول',variants:'تنوع محصول و SKU'})[tab]}</h1><p class="admin-help">برای ایجاد محصول جدید از بخش «محصولات» همین پنل استفاده کنید.</p><select id="product-selector" class="admin-select"><option value="">انتخاب محصول...</option>${catalog.map(p=>`<option value="${esc(p.id)}">${esc(p.name)}</option>`).join('')}</select><div id="product-editor"></div>`;}
 async function productChanged(id){const target=document.getElementById('product-editor');if(!id){target.innerHTML='';return;}try{
 if(tab==='seo'){const d=await request('seo&product_id='+id);const s=d.seo||{};target.innerHTML='<form id="seo-form" class="admin-form" data-product="'+esc(id)+'">'+formText('عنوان SEO','seo_title',s.seo_title||'')+formText('Meta description','meta_description',s.meta_description||'')+formText('نام برند','brand',s.brand||'')+formText('شرح سئویی/بررسی تخصصی','seo_body',s.seo_body||'','textarea')+formText('FAQ JSON','faq_json',s.faq_json||'[]','textarea')+formText('Canonical URL (اختیاری)','canonical_url',s.canonical_url||'')+formText('OG image URL','og_image',s.og_image||'')+`<label>ایندکس بعد از آماده‌شدن سایت<select name="robots_policy" class="admin-select"><option value="noindex" ${s.robots_policy==='noindex'?'selected':''}>noindex</option><option value="index" ${s.robots_policy==='index'?'selected':''}>index</option></select></label><p><button class="primary-btn">ذخیره محتوای سئو</button></p></form>`;}
 if(tab==='media'){const d=await request('media&product_id='+id);target.innerHTML='<h2>رسانه‌های ثبت‌شده</h2>'+rows(d.items,['نوع','عنوان','آدرس','عملیات'],m=>`<tr><td>${esc(m.media_type)}</td><td>${esc(m.title)}</td><td><a href="${esc(m.url)}" rel="noopener" target="_blank">مشاهده رسانه</a></td><td><button class="light-btn" data-act="delete-media" data-id="${m.id}">حذف</button></td></tr>`)+`<form id="media-form" class="admin-form" data-product="${esc(id)}"><h3>افزودن عکس یا ویدیو</h3><label>نوع<select name="media_type" class="admin-select"><option value="image">تصویر</option><option value="video">ویدیو</option></select></label>`+formText('آدرس HTTPS یا تصویر آپلودشده','url')+formText('تصویر پوستر ویدیو','poster_url')+formText('توضیح تصویر (alt)','alt_text')+formText('عنوان','title')+formText('ترتیب نمایش','sort_order',0,'number')+'<p><button class="primary-btn">ثبت رسانه</button></p></form><p class="admin-help">آپلود تصویر از پنل کالا امکان‌پذیر است؛ ویدیو فعلاً باید روی فضای امن HTTPS میزبانی شود.</p>';}
 if(tab==='variants'){const d=await request('variants&product_id='+id);target.innerHTML='<h2>تنوع‌های ثبت‌شده</h2>'+rows(d.items,['SKU','عنوان','قیمت','موجودی'],v=>`<tr><td>${esc(v.sku)}</td><td>${esc(v.title)}</td><td>${money(v.price)}</td><td>${v.stock}</td></tr>`)+`<form id="variant-form" class="admin-form" data-product="${esc(id)}"><h3>افزودن تنوع جدید</h3>`+formText('SKU یکتا','sku')+formText('عنوان تنوع (رنگ/سایز/مدل)','title')+formText('ویژگی‌ها (JSON)','attributes_json','{}','textarea')+formText('قیمت (تومان)','price',0,'number')+formText('قیمت قبلی','compare_price',0,'number')+formText('موجودی','stock',0,'number')+formText('وزن به گرم','weight_grams',0,'number')+'<label><input name="active" type="checkbox" checked> فعال</label><p><button class="primary-btn">ثبت تنوع</button></p></form>';}
 }catch(e){target.innerHTML='<p class="api-msg error">'+esc(e.message)+'</p>'}}
 function blockForm(b,i){return `<div class="block-edit" data-index="${i}"><div class="admin-top"><strong>بلوک ${i+1} — ${esc(b.type)}</strong><div class="admin-buttons"><button class="light-btn" data-block="up" data-i="${i}">بالا</button><button class="light-btn" data-block="down" data-i="${i}">پایین</button><button class="light-btn" data-block="delete" data-i="${i}">حذف</button></div></div><label>نوع بلوک<select name="type">${['hero','categories','products','magazine','banner','text','faq'].map(t=>`<option value="${t}" ${t===b.type?'selected':''}>${t}</option>`).join('')}</select></label><label>عنوان<input name="title" value="${esc(b.title||'')}"></label><label>توضیح<textarea name="text">${esc(b.text||'')}</textarea></label><label>تصویر HTTPS / آپلود<input name="image" value="${esc(b.image||'')}"></label><label>لینک CTA<input name="link" value="${esc(b.link||'')}"></label><label>شناسه محصولات انتخابی با کاما<input name="product_ids" value="${esc((b.product_ids||[]).join(','))}"></label></div>`}
 function readBlocks(){blocks=[...root.querySelectorAll('.block-edit')].map(el=>Object.fromEntries([...el.querySelectorAll('[name]')].map(x=>[x.name,x.name==='product_ids'?x.value.split(',').map(x=>x.trim()).filter(Boolean):x.value])));}
 function paintBlocks(){const area=$('#blocks-editor');if(area)area.innerHTML=blocks.map(blockForm).join('');}
 async function drawPages(){const d=await request('pages');root.innerHTML='<h1>صفحه‌ساز فروشگاه</h1><p class="notice">پس از انتشار لندینگ، صفحه اصلی فروشگاه از همین محتوای سروری نمایش داده می‌شود.</p><div class="admin-buttons"><button class="light-btn" id="new-page">+ ساخت صفحه جدید</button></div><select class="admin-select" id="page-selector">'+d.items.map(p=>`<option value="${esc(p.slug)}" ${p.slug===pageSlug?'selected':''}>${esc(p.title)} — ${esc(p.slug)}</option>`).join('')+'</select><div id="page-editor"></div>';await pageChanged(pageSlug);}
 async function pageChanged(slug){pageSlug=slug;const d=await request('page&slug='+slug);let p=d.page;blocks=Array.isArray(p.blocks)?p.blocks:[];let box=$('#page-editor');box.innerHTML=`<label>عنوان صفحه<input class="admin-input" id="page-title" value="${esc(p.title)}"></label><label>Meta description<input class="admin-input" id="page-description" value="${esc(p.meta_description)}"></label><div class="admin-top"><h2>ترتیب سکشن‌های لندینگ</h2><select id="block-type" class="admin-select" style="max-width:170px">${['hero','categories','products','magazine','banner','text','faq'].map(t=>`<option value="${t}">${t}</option>`).join('')}</select><button class="light-btn" id="add-block">+ بلوک</button></div><div id="blocks-editor"></div><div class="admin-buttons"><button id="save-page" class="light-btn">ذخیره پیش‌نویس</button><button id="publish-page" class="primary-btn">انتشار صفحه</button><a class="light-btn" href="/storefront.php" target="_blank" rel="noopener">پیش‌نمایش منتشرشده</a></div><div id="page-versions" class="block-section"></div>`;paintBlocks();try{const versions=await request('page.versions&slug='+slug);$('#page-versions').innerHTML='<h3>تاریخچه انتشار</h3>'+versions.versions.map(v=>`<div class="review">نسخه ${v.version_num} — ${esc(v.created_at)} <button class="light-btn" data-act="restore-page" data-version="${v.version_num}">بازیابی در پیش‌نویس</button></div>`).join('');}catch(e){/* history optional in staging */}}
 document.addEventListener('click',async e=>{const b=e.target.closest('button');if(!b)return;
  if(b.dataset.tab){await switchTab(b.dataset.tab);return;}
  if(b.dataset.block){readBlocks();let i=Number(b.dataset.i);if(b.dataset.block==='delete')blocks.splice(i,1);if(b.dataset.block==='up'&&i>0)[blocks[i-1],blocks[i]]=[blocks[i],blocks[i-1]];if(b.dataset.block==='down'&&i<blocks.length-1)[blocks[i+1],blocks[i]]=[blocks[i],blocks[i+1]];paintBlocks();return;}
  if(b.id==='new-page'){e.preventDefault();const slug=prompt('نامک صفحه جدید (انگلیسی و خط تیره)');if(!slug)return;const title=prompt('عنوان صفحه جدید');if(!title)return;try{await request('page.save',{slug,title,meta_description:'',blocks:[]});pageSlug=slug;await drawPages();await pageChanged(slug);}catch(err){msg(err.message,true)}return;}
  if(b.dataset.act==='restore-page'){try{await request('page.restore',{slug:pageSlug,version:Number(b.dataset.version)});await pageChanged(pageSlug);msg('نسخه بازیابی شد. برای انتشار مجدد، دکمه انتشار را بزنید.');}catch(err){msg(err.message,true)}return;}
  if(b.id==='add-block'){e.preventDefault();readBlocks();blocks.push({type:$('#block-type').value,title:'',text:'',image:'',link:'',product_ids:[]});paintBlocks();return;}
  if(b.id==='save-page'||b.id==='publish-page'){try{readBlocks();await request('page.save',{slug:pageSlug,title:$('#page-title').value,meta_description:$('#page-description').value,blocks});if(b.id==='publish-page')await request('page.publish',{slug:pageSlug});msg(b.id==='save-page'?'پیش‌نویس ذخیره شد.':'صفحه منتشر شد.');}catch(err){msg(err.message,true)}return;}
  if(!b.dataset.act)return;
  try{if(b.dataset.act==='fulfillment')await request('order.fulfillment',{id:Number(b.dataset.id),status:$(`.fulfillment-select[data-id="${b.dataset.id}"]`).value});
   if(b.dataset.act==='moderate')await request('review.moderate',{id:Number(b.dataset.id),status:b.dataset.status});
   if(b.dataset.act==='question')await request('question.moderate',{id:Number(b.dataset.id),status:'approved',answer:$(`.q-answer[data-id="${b.dataset.id}"]`).value});
   if(b.dataset.act==='return')await request('return.update',{id:Number(b.dataset.id),status:$(`.return-select[data-id="${b.dataset.id}"]`).value});
   if(b.dataset.act==='reply')await request('ticket.reply',{id:Number(b.dataset.id),reply:$(`.ticket-reply[data-id="${b.dataset.id}"]`).value,status:'resolved'});
   if(b.dataset.act==='inventory'){await request('inventory.adjust',{product_id:b.dataset.id,delta:Number($(`.inventory-delta[data-id="${b.dataset.id}"]`).value),reason:$(`.inventory-reason[data-id="${b.dataset.id}"]`).value});await switchTab('inventory');return;}
   if(b.dataset.act==='delete-media'){await request('media.delete',{id:Number(b.dataset.id)});await productChanged($('#product-selector').value);return;}
   msg('تغییر در دیتابیس ذخیره شد.');}catch(err){msg(err.message,true)}
 });
 document.addEventListener('change',async e=>{if(e.target.id==='product-selector')await productChanged(e.target.value);if(e.target.id==='page-selector')await pageChanged(e.target.value)});
 document.addEventListener('submit',async e=>{const f=e.target;if(!['seo-form','media-form','variant-form','coupon-form'].includes(f.id))return;e.preventDefault();const data=Object.fromEntries(new FormData(f).entries());
  try{if(f.id==='seo-form')await request('seo.save',{...data,product_id:f.dataset.product});
  if(f.id==='media-form')await request('media.save',{...data,product_id:f.dataset.product});
  if(f.id==='variant-form')await request('variant.save',{...data,product_id:f.dataset.product,active:!!data.active});
  if(f.id==='coupon-form')await request('coupon.save',{code:data.code,type:data.type,value:Number(data.value),min_total:Number(data.min_total),usage_limit:Number(data.usage_limit),active:!!data.active});
  msg('اطلاعات ذخیره شد.');if(['media-form','variant-form'].includes(f.id))await productChanged(f.dataset.product);
  }catch(err){msg(err.message,true)}
 });
// Integrated admin catalog: reuses the already secured Sprint 01 API and session.
 let fullCatalog={categories:[],products:[]}, editingCatalog=null, catalogSearch='';
 const safeUrl=url=>(/^https:\/\//.test(url||'')||/^\/uploads\/[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif)$/.test(url||''))?esc(url):'';
 async function requestCatalog(action,body,form=false){
  const options={method:body===undefined?'GET':'POST',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}};
  if(body!==undefined){options.headers['X-CSRF-Token']=csrf;if(form)options.body=body;else{options.headers['Content-Type']='application/json';options.body=JSON.stringify(body);}}
  const r=await fetch('/api/admin.php?action='+encodeURIComponent(action),options);
  const d=await r.json().catch(()=>({ok:false,error:'پاسخ API مدیریت معتبر نیست.'}));
  if(!r.ok||!d.ok)throw Error(d.error||'خطای ارتباط با مدیریت کالا');
  return d;
 }
 async function loadCatalogAdmin(){fullCatalog=await requestCatalog('bootstrap');return fullCatalog;}
 function catThumb(url,emoji='📦'){
  const safe=safeUrl(url);
  return safe?`<img class="thumb" src="${safe}" alt="">`:`<span style="font-size:25px">${esc(emoji||'📦')}</span>`;
 }
 function itemField(name,label,value='',type='text',extras=''){
  return `<label>${esc(label)}<input name="${esc(name)}" type="${type}" value="${esc(value)}" ${extras}></label>`;
 }
 function itemArea(name,label,value=''){
  return `<label class="wide">${esc(label)}<textarea name="${esc(name)}">${esc(value)}</textarea></label>`;
 }
 function itemCheck(name,label,value){return `<label><input name="${esc(name)}" type="checkbox" ${value?'checked':''}> ${esc(label)}</label>`;}
 function imageField(value=''){
  return `<label class="wide">تصویر اصلی کالا یا دسته<div class="admin-file-field"><input id="catalog-image" name="image" value="${esc(value)}" placeholder="آدرس HTTPS یا /uploads/..."><input type="file" id="catalog-file" accept="image/png,image/jpeg,image/webp,image/gif"></div><small>عکس JPG/PNG/WebP/GIF، حداکثر ۴ مگابایت · پس از آپلود محصول را ذخیره کنید.</small></label>`;
 }
 function catalogForm(kind,id=''){
  editingCatalog={kind,id};
  const cat=kind==='category',arr=cat?fullCatalog.categories:fullCatalog.products;
  const item=id?arr.find(x=>x.id===id):{};
  if(id&&!item){msg('مورد انتخاب‌شده یافت نشد.',true);return;}
  let fields='';
  if(cat){fields=
   itemField('name','نام دسته',item.name||'','text','required maxlength="190"')+
   itemField('slug','نامک انگلیسی دسته',item.slug||'','text','required pattern="[a-z0-9]+(-[a-z0-9]+)*"')+
   `<label>دسته والد<select name="parent"><option value="">دسته اصلی</option>${fullCatalog.categories.filter(x=>x.id!==id).map(c=>`<option value="${esc(c.id)}" ${c.id===item.parent?'selected':''}>${esc(c.name)}</option>`).join('')}</select></label>`+
   itemField('order','ترتیب نمایش',item.order||0,'number','min="0"')+
   itemField('emoji','نماد دسته',item.emoji||'')+imageField(item.image||'')+
   itemArea('description','توضیحات دسته',item.description||'')+
   itemCheck('active','نمایش دسته در فروشگاه',item.active!==false);
  }else{fields=
   itemField('name','نام محصول',item.name||'','text','required maxlength="190"')+
   itemField('slug','نامک انگلیسی URL',item.slug||'','text','required pattern="[a-z0-9]+(-[a-z0-9]+)*"')+
   itemField('sku','کد یکتای محصول SKU',item.sku||'','text','required maxlength="80"')+
   `<label>دسته محصول<select name="category" required>${fullCatalog.categories.map(c=>`<option value="${esc(c.id)}" ${item.category===c.id?'selected':''}>${esc(c.name)}</option>`).join('')}</select></label>`+
   itemField('price','قیمت فروش (تومان)',item.price||0,'number','required min="0"')+
   itemField('comparePrice','قیمت قبلی (تومان)',item.comparePrice||0,'number','min="0"')+
   itemField('stock','موجودی',item.stock||0,'number','min="0"')+
   itemField('emoji','نماد جایگزین',item.emoji||'📦')+imageField(item.image||'')+
   itemField('gallery','آدرس‌های گالری با کاما',item.gallery||'')+
   itemField('video','آدرس ویدیو HTTPS',item.video||'')+
   itemArea('description','شرح کالا',item.description||'')+
   itemArea('specs','مشخصات فنی',item.specs||'')+
   itemCheck('published','انتشار کالا در فروشگاه',!!item.published)+
   itemCheck('featured','محصول منتخب صفحه اصلی',!!item.featured);
  }
  const existing=document.getElementById('catalog-editor');if(existing)existing.remove();
  const container=document.createElement('div');container.id='catalog-editor';container.className='admin-editor';
  container.innerHTML=`<h2>${id?'ویرایش':'افزودن'} ${cat?'دسته':'محصول'}</h2><form id="catalog-form"><div class="admin-editor-grid">${fields}</div><div class="admin-editor-actions"><button type="submit" class="primary-btn">ذخیره در دیتابیس</button><button type="button" data-cat-action="cancel" class="light-btn">انصراف</button></div></form>`;
  root.prepend(container);container.scrollIntoView({behavior:'smooth',block:'start'});
 }
 function catalogRow(p){const title=esc(p.name);const category=fullCatalog.categories.find(c=>c.id===p.category)?.name||'';
  return `<tr><td>${catThumb(p.image,p.emoji)} <b>${title}</b></td><td>${esc(p.sku)}</td><td>${esc(category)}</td><td>${money(p.price)}</td><td>${esc(p.stock)}</td><td>${p.published?'منتشرشده':'پیش‌نویس'}</td><td><button class="light-btn" type="button" data-cat-action="edit" data-kind="product" data-id="${esc(p.id)}">ویرایش</button><button class="light-btn" type="button" data-cat-action="delete" data-kind="product" data-id="${esc(p.id)}">حذف</button></td></tr>`;}
 function catalogCategoryRow(c){return `<tr><td>${catThumb(c.image,c.emoji)} <b>${esc(c.name)}</b></td><td>${esc(c.slug)}</td><td>${esc(fullCatalog.categories.find(p=>p.id===c.parent)?.name||'اصلی')}</td><td>${esc(c.order)}</td><td>${c.active?'فعال':'غیرفعال'}</td><td><button class="light-btn" type="button" data-cat-action="edit" data-kind="category" data-id="${esc(c.id)}">ویرایش</button><button class="light-btn" type="button" data-cat-action="delete" data-kind="category" data-id="${esc(c.id)}">حذف</button></td></tr>`;}
 function catalogFilter(){const b=document.getElementById('catalog-rows');if(!b)return;
  const q=catalogSearch.trim().toLocaleLowerCase('fa');
  const filtered=fullCatalog.products.filter(p=>[p.name,p.sku,p.slug].join(' ').toLocaleLowerCase('fa').includes(q));
  b.innerHTML=filtered.map(catalogRow).join('')||'<tr><td colspan="7">محصولی یافت نشد.</td></tr>';
 }
 async function drawCatalog(){await loadCatalogAdmin();editingCatalog=null;catalogSearch='';
  if(tab==='products'){
   root.innerHTML=`<div class="admin-top"><h1>مدیریت محصولات</h1><button type="button" class="primary-btn" data-cat-action="new" data-kind="product">+ محصول جدید</button></div><p class="admin-help">ثبت کالا، قیمت، موجودی و تصویر در پایگاه داده؛ گالری و سئو از منوی همین پنل مدیریت می‌شوند.</p><input id="catalog-search" class="admin-input" placeholder="جست‌وجو در نام و SKU" autocomplete="off"><div class="admin-panel-table"><table><thead><tr><th>محصول</th><th>SKU</th><th>دسته</th><th>قیمت</th><th>موجودی</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody id="catalog-rows"></tbody></table></div>`;
   catalogFilter();
  }else root.innerHTML=`<div class="admin-top"><h1>مدیریت دسته‌بندی‌ها</h1><button type="button" class="primary-btn" data-cat-action="new" data-kind="category">+ دسته جدید</button></div><p class="admin-help">دسته والد، ترتیب نمایش، تصویر و توضیحات در تمام ویترین به‌صورت مرکزی ثبت می‌شوند.</p><div class="admin-panel-table"><table><thead><tr><th>دسته</th><th>نامک</th><th>والد</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>${fullCatalog.categories.map(catalogCategoryRow).join('')}</tbody></table></div>`;
 }
 function adminLogin(errorText=''){
  document.getElementById('admin-workspace').hidden=true;
  document.getElementById('admin-account-menu').hidden=true;
  const stage=document.getElementById('admin-login-stage');stage.hidden=false;
  stage.innerHTML=`<div class="admin-login-card"><img src="/assets/myca-logo.svg" alt="مایکا"><h1>ورود به مدیریت یکپارچه مایکا</h1><p class="muted">برای مدیریت محصولات، محتوا و سفارش‌ها وارد شوید.</p><p class="notice">در محیط آزمایشی با گواهی نامعتبر از رمز واقعی و اطلاعات شخصی استفاده نکنید. برای ورود ایمن HTTPS معتبر لازم است.</p><form id="unified-login-form"><label>ایمیل مدیریت<input class="admin-input" name="email" type="email" autocomplete="username" required></label><label>رمز عبور<input class="admin-input" name="password" type="password" autocomplete="current-password" required></label><p id="unified-login-error" class="admin-login-error">${esc(errorText)}</p><button class="primary-btn" type="submit">ورود به پنل</button></form></div>`;
 }
 function adminWorkspace(email=''){
  document.getElementById('admin-login-stage').hidden=true;
  document.getElementById('admin-workspace').hidden=false;
  document.getElementById('admin-account-menu').hidden=false;
  document.getElementById('admin-session-email').textContent=email;
 }
 async function adminBoot(){
  try{const session=await baseSession();if(session.authenticated){adminWorkspace(session.admin?.email||'');await switchTab('dashboard');}else adminLogin();}
  catch(e){adminLogin(e.message||'خطای ارتباط با مدیریت');}
 }
 document.addEventListener('submit',async e=>{
  if(e.target.id==='unified-login-form'){
   e.preventDefault();const form=e.target;const submit=form.querySelector('button[type=submit]');submit.disabled=true;
   try{const f=new FormData(form);const d=await requestCatalog('login',{email:f.get('email'),password:f.get('password')});csrf=d.csrf;form.reset();adminWorkspace(d.admin?.email||'');await switchTab('dashboard');}
   catch(err){const el=document.getElementById('unified-login-error');if(el)el.textContent=err.message;}
   finally{submit.disabled=false;}
   return;
  }
  if(e.target.id==='catalog-form'){
   e.preventDefault();if(!editingCatalog)return;
   const f=new FormData(e.target),type=editingCatalog.kind;
   const raw=Object.fromEntries(f.entries());delete raw.imageUpload;
   const vals={...raw,id:editingCatalog.id};
   if(type==='category'){vals.active=f.has('active');vals.order=Number(vals.order||0);}
   else{vals.published=f.has('published');vals.featured=f.has('featured');vals.price=Number(vals.price||0);vals.comparePrice=Number(vals.comparePrice||0);vals.stock=Number(vals.stock||0);}
   const button=e.target.querySelector('button[type=submit]');button.disabled=true;
   try{await requestCatalog(type+'.save',vals);editingCatalog=null;await drawCatalog();msg('اطلاعات ذخیره شد.');}
   catch(err){msg(err.message,true);button.disabled=false;}
  }
 });
 document.addEventListener('click',async e=>{
  if(e.target.closest('#admin-logout')){
   try{await requestCatalog('logout',{});csrf='';await baseSession();adminLogin();}catch(err){msg(err.message,true);}return;
  }
  const b=e.target.closest('[data-cat-action]');if(!b)return;
  const action=b.dataset.catAction,id=b.dataset.id||'',kind=b.dataset.kind||'product';
  if(action==='new')catalogForm(kind);
  if(action==='edit')catalogForm(kind,id);
  if(action==='cancel'){document.getElementById('catalog-editor')?.remove();editingCatalog=null;}
  if(action==='delete'){
   if(!confirm('این مورد از فروشگاه حذف شود؟ این عملیات قابل بازگشت نیست.'))return;
   try{await requestCatalog(kind+'.delete',{id});await drawCatalog();msg('حذف شد.');}catch(err){msg(err.message,true);}
  }
 });
 document.addEventListener('input',e=>{if(e.target.id==='catalog-search'){catalogSearch=e.target.value;catalogFilter();}});
 document.addEventListener('change',async e=>{
  if(e.target.id!=='catalog-file')return;
  const file=e.target.files?.[0];if(!file)return;
  if(file.size>4*1024*1024){msg('حجم تصویر باید حداکثر ۴ مگابایت باشد.',true);return;}
  try{const form=new FormData();form.set('image',file);const data=await requestCatalog('media.upload',form,true);const input=document.getElementById('catalog-image');if(input)input.value=data.url;msg('تصویر آپلود شد؛ حالا دکمه ذخیره را بزن.');}
  catch(err){msg(err.message,true);}
 });

 adminBoot();
})();
