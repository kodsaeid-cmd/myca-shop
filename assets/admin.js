'use strict';
const API='/api/admin.php?action=';
const root=document.getElementById('admin-root');
let csrf='', sessionAdmin=null, catalog={categories:[],products:[]}, tab='overview', editing=null, search='', busy=false;
const esc=s=>String(s??'').replace(/[&<>"']/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[x]));
const fa=n=>new Intl.NumberFormat('fa-IR').format(Number(n)||0);
const src=s=>(/^https:\/\//i.test(s||'')||/^\/uploads\/[a-f0-9]{32}\.(?:png|jpg|webp|gif)$/.test(s||''))?esc(s):'';
function toast(t){document.querySelector('.toast')?.remove();const d=document.createElement('div');d.className='toast';d.textContent=t;document.body.appendChild(d);setTimeout(()=>d.remove(),3500)}
async function request(action,body,asForm=false){
 const method=body===undefined?'GET':'POST';
 const options={method,credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json'}};
 if(method==='POST'){options.headers['X-CSRF-Token']=csrf;
  if(asForm){options.body=body;}else{options.headers['Content-Type']='application/json';options.body=JSON.stringify(body)}
 }
 const response=await fetch(API+encodeURIComponent(action),options);
 let data;try{data=await response.json()}catch{throw Error('پاسخ سرور معتبر نیست.');}
 if(!response.ok||!data.ok)throw Error(data.error||'ارتباط با سرور برقرار نشد.');
 return data;
}
async function boot(){try{const info=await request('session');csrf=info.csrf;sessionAdmin=info.admin;if(sessionAdmin)await loadCatalog();render();}catch(e){root.innerHTML=`<div class="login-wrap"><div class="login"><h1>اتصال به سرور برقرار نشد</h1><p class="alert">${esc(e.message)}</p><p class="muted small">وضعیت فایل app/config.local.php و جداول MySQL را بررسی کنید.</p></div></div>`;}}
async function loadCatalog(){const data=await request('bootstrap');catalog={categories:data.categories,products:data.products};}
function tagLine(){return '<div class="top-strip">مایکا، همراه زندگی · مدیریت یکپارچه فروشگاه</div>';}
function header(){return `${tagLine()}<header class="site-head"><div class="brand"><img src="/assets/myca-logo.svg" alt="MYCA"><b>مرکز مدیریت فروشگاه</b></div><div class="row"><span class="muted small">${esc(sessionAdmin.email)}</span><button class="btn secondary" data-action="open-shop">مشاهده فروشگاه</button><button class="btn secondary" data-action="logout">خروج امن</button></div></header>`;}
function login(){return `${tagLine()}<div class="login-wrap"><div class="login"><img src="/assets/myca-logo.svg" alt="MYCA"><span class="eyebrow">MYCA COMMERCE ADMIN</span><h1>ورود به پنل فروشگاه</h1><p class="muted small">مدیریت مرکزی کالاها، دسته‌بندی‌ها و رسانه‌ها</p><form data-form="login"><div class="field"><label>ایمیل مدیر</label><input name="email" type="email" autocomplete="username" required></div><div class="field"><label>رمز عبور</label><input name="password" type="password" autocomplete="current-password" required></div><div id="login-error" aria-live="polite"></div><button class="btn" style="width:100%;margin-top:17px" type="submit">ورود به مدیریت</button></form><p class="muted small" style="margin-top:20px">برای ایجاد حساب مدیر از اسکریپت خط فرمان استفاده می‌شود. ثبت‌نام عمومی در این پنل وجود ندارد.</p></div></div>`;}
function dashboard(){return `<div class="grid-cards"><div class="stat"><small>تعداد محصولات</small><strong>${fa(catalog.products.length)}</strong><small>تمام وضعیت‌ها</small></div><div class="stat"><small>محصولات منتشرشده</small><strong>${fa(catalog.products.filter(p=>p.published).length)}</strong><small>قابل نمایش در ویترین</small></div><div class="stat"><small>دسته‌بندی‌های فعال</small><strong>${fa(catalog.categories.filter(c=>c.active).length)}</strong><small>با قابلیت تنظیم ترتیب</small></div></div><div class="panel" style="margin-top:19px"><span class="eyebrow">Sprint 01 · COMMERCE FOUNDATION</span><h2>پنل به پایگاه داده متصل است</h2><p class="muted">ایجاد، ویرایش و حذف محصولات و دسته‌بندی‌ها از طریق API و MySQL انجام می‌شود. پس از ذخیره، تغییرات برای تمام بازدیدکنندگان فروشگاه قابل دریافت است.</p><div class="row"><button class="btn" data-action="tab" data-tab="products">مدیریت محصولات</button><button class="btn secondary" data-action="tab" data-tab="categories">مدیریت دسته‌بندی‌ها</button></div></div><div class="notice">این اسپرینت هنوز سفارش، پرداخت، حساب مشتری و صفحه‌ساز سروری ندارد؛ این بخش‌ها نباید به‌عنوان امکانات عملیاتی معرفی شوند.</div>`}
function thumb(row){const url=src(row.image);return url?`<img class="thumb" alt="" src="${url}">`:`<div class="thumb">${esc(row.emoji||'📦')}</div>`;}
function listProducts(){let list=catalog.products.filter(p=>`${p.name} ${p.sku} ${p.slug}`.toLocaleLowerCase('fa').includes(search.toLocaleLowerCase('fa')));return `<div class="panel"><div class="panel-head"><div><h2>محصولات فروشگاه</h2><p class="muted small">تغییرات در پایگاه داده ذخیره می‌شوند.</p></div><div class="row"><input class="search desktop-filter" id="search-products" value="${esc(search)}" placeholder="جست‌وجوی نام یا SKU"><button class="btn" data-action="new-product">+ محصول جدید</button></div></div><div class="table-scroll"><table class="table"><thead><tr><th>محصول</th><th>SKU</th><th>دسته</th><th>قیمت (تومان)</th><th>موجودی</th><th>نمایش</th><th>عملیات</th></tr></thead><tbody>${list.map(p=>`<tr><td><div class="row">${thumb(p)}<span class="table-title">${esc(p.name)}</span></div></td><td class="num">${esc(p.sku)}</td><td>${esc(catalog.categories.find(c=>c.id===p.category)?.name||'—')}</td><td>${fa(p.price)}</td><td>${fa(p.stock)}</td><td><span class="pill ${p.published?'':'off'}">${p.published?'منتشرشده':'پیش‌نویس'}</span></td><td><div class="row"><button class="btn secondary tiny" data-action="edit-product" data-id="${esc(p.id)}">ویرایش</button><button class="btn danger tiny" data-action="delete-product" data-id="${esc(p.id)}">حذف</button></div></td></tr>`).join('')||'<tr><td colspan="7" class="empty">محصولی پیدا نشد.</td></tr>'}</tbody></table></div></div>`;}
function listCategories(){return `<div class="panel"><div class="panel-head"><div><h2>دسته‌بندی محصولات</h2><p class="muted small">درخت دسته‌ها، نمایش و ترتیب</p></div><button class="btn" data-action="new-category">+ دسته جدید</button></div><div class="table-scroll"><table class="table"><thead><tr><th>نام دسته</th><th>نامک</th><th>دسته والد</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>${catalog.categories.map(c=>`<tr><td><div class="row">${thumb(c)}<strong>${esc(c.name)}</strong></div></td><td class="num">${esc(c.slug)}</td><td>${esc(catalog.categories.find(x=>x.id===c.parent)?.name||'دسته اصلی')}</td><td>${fa(c.order)}</td><td><span class="pill ${c.active?'':'off'}">${c.active?'فعال':'غیرفعال'}</span></td><td><div class="row"><button class="btn secondary tiny" data-action="edit-category" data-id="${esc(c.id)}">ویرایش</button><button class="btn danger tiny" data-action="delete-category" data-id="${esc(c.id)}">حذف</button></div></td></tr>`).join('')||'<tr><td colspan="6" class="empty">هنوز دسته‌ای ثبت نشده است.</td></tr>'}</tbody></table></div></div>`;}
function field(name,label,value='',type='text',extra=''){return `<div class="field"><label for="f_${name}">${esc(label)}</label><input id="f_${name}" name="${name}" type="${type}" value="${esc(value)}" ${extra}></div>`;}
function area(name,label,value='',className=''){return `<div class="field ${className}"><label for="f_${name}">${esc(label)}</label><textarea id="f_${name}" name="${name}">${esc(value)}</textarea></div>`;}
function check(name,label,value){return `<div class="field"><label class="check"><input type="checkbox" name="${name}" ${value?'checked':''}> ${esc(label)}</label></div>`;}
function imageField(value){const url=src(value);return `<div class="field span2"><label>تصویر اصلی</label><div class="row"><input type="text" name="image" id="image-url" value="${esc(value)}" placeholder="https://... یا تصویر آپلودشده" style="flex:1;min-width:160px"><input type="file" id="image-upload" accept="image/jpeg,image/png,image/webp,image/gif" style="max-width:230px"></div><small class="muted">آپلود مستقیم JPG/PNG/WebP/GIF تا ۴ مگابایت، یا واردکردن URL معتبر HTTPS.</small>${url?`<img src="${url}" class="media-preview" alt="پیش‌نمایش تصویر">`:''}</div>`;}
function editorForm(){if(!editing)return '';const item=editing.row, cat=editing.kind==='category';const title=editing.id?'ویرایش':'ایجاد';let inner;
 if(cat){inner=`${field('name','نام دسته',item.name,'text','required maxlength="190"')}${field('slug','نامک انگلیسی',item.slug,'text','required pattern="[a-z0-9]+(-[a-z0-9]+)*"')}<div class="field"><label>دسته والد</label><select name="parent"><option value="">دسته اصلی</option>${catalog.categories.filter(x=>x.id!==editing.id).map(x=>`<option value="${esc(x.id)}" ${x.id===item.parent?'selected':''}>${esc(x.name)}</option>`).join('')}</select></div>${field('order','ترتیب نمایش',item.order||0,'number','min="0"')}${field('emoji','آیکون / ایموجی',item.emoji)}${imageField(item.image||'')}${area('description','توضیحات',item.description,'span2')}${check('active','نمایش دسته در فروشگاه',item.active)}`;}
 else {inner=`${field('name','نام کالا',item.name,'text','required maxlength="190"')}${field('slug','نامک URL انگلیسی',item.slug,'text','required pattern="[a-z0-9]+(-[a-z0-9]+)*"')}${field('sku','کد یکتای کالا (SKU)',item.sku,'text','required maxlength="80"')}<div class="field"><label>دسته</label><select name="category" required>${catalog.categories.map(x=>`<option value="${esc(x.id)}" ${x.id===item.category?'selected':''}>${esc(x.name)}</option>`).join('')}</select></div>${field('price','قیمت فروش (تومان)',item.price||0,'number','required min="0"')}${field('comparePrice','قیمت قبل از تخفیف',item.comparePrice||0,'number','min="0"')}${field('stock','موجودی فعلی',item.stock||0,'number','min="0"')}${field('emoji','نماد جایگزین عکس',item.emoji||'📦')}${imageField(item.image||'')}${field('gallery','آدرس تصاویر گالری (با کاما جدا کنید)',item.gallery||'')}${field('video','آدرس HTTPS ویدیوی بررسی',item.video||'')}${area('description','توضیحات کالا',item.description||'','span2')}${area('specs','مشخصات فنی',item.specs||'','span2')}${check('published','انتشار محصول در فروشگاه',item.published)}${check('featured','محصول منتخب',item.featured)}`;}
 return `<div class="dialog-backdrop" data-action="close-backdrop"><div class="dialog"><div class="panel-head"><h2>${title} ${cat?'دسته':'محصول'}</h2><button class="btn secondary tiny" data-action="close-editor">بستن ×</button></div><form data-form="${cat?'category':'product'}"><div class="form-grid">${inner}</div><div class="row" style="margin-top:24px"><button type="submit" class="btn">ذخیره در پایگاه داده</button><button type="button" class="btn secondary" data-action="close-editor">انصراف</button></div></form></div></div>`;}
function render(){if(!sessionAdmin){root.innerHTML=login();return;}
 const nav=[['overview','نمای کلی'],['products','محصولات'],['categories','دسته‌بندی‌ها']];
 root.innerHTML=`${header()}<div class="layout"><aside class="sidebar">${nav.map(([id,label])=>`<button data-action="tab" data-tab="${id}" class="${tab===id?'active':''}">${label}</button>`).join('')}</aside><main><div class="panel-head" style="margin:5px 0 20px"><div><span class="eyebrow">MYCA SHOP · ADMIN</span><h1 style="margin-bottom:0">${tab==='overview'?'مدیریت فروشگاه':tab==='products'?'کاتالوگ محصولات':'ساختار دسته‌بندی‌ها'}</h1></div><button class="btn secondary" data-action="reload">به‌روزرسانی اطلاعات</button></div>${tab==='overview'?dashboard():tab==='products'?listProducts():listCategories()}<div class="footer">© MYCA — پنل اختصاصی فروشگاه · پشتیبانی ۴۱۱۲۴</div></main></div>${editorForm()}`;
}
function edit(kind,id){let found=kind==='category'?catalog.categories.find(x=>x.id===id):catalog.products.find(x=>x.id===id);
 const row=found?{...found}:kind==='category'?{name:'',slug:'',parent:'',emoji:'📁',image:'',description:'',order:0,active:true}:{name:'',slug:'',sku:'',category:catalog.categories[0]?.id||'',price:0,comparePrice:0,stock:0,emoji:'📦',image:'',gallery:'',video:'',description:'',specs:'',published:false,featured:false};
 editing={kind,id:id||'',row};render();}
async function saveForm(kind,form){if(busy)return;busy=true;const button=form.querySelector('[type=submit]');if(button)button.disabled=true;
 const raw=Object.fromEntries(new FormData(form).entries());
 raw.id=editing.id;
 if(kind==='category'){raw.active=form.elements.active.checked;raw.order=String(raw.order||0);}
 else{raw.published=form.elements.published.checked;raw.featured=form.elements.featured.checked;}
 try{await request(kind+'.save',raw);editing=null;await loadCatalog();render();toast('اطلاعات با موفقیت در سرور ذخیره شد.');}
 catch(e){toast(e.message);if(button)button.disabled=false;}finally{busy=false;}
}
async function imageUpload(file){if(!file)return;if(file.size>4*1024*1024){toast('حجم تصویر از ۴ مگابایت بیشتر است.');return;}
 const data=new FormData();data.append('image',file);
 try{const r=await request('media.upload',data,true);const el=root.querySelector('#image-url');if(el)el.value=r.url;toast('تصویر در سرور آپلود شد.');}
 catch(e){toast(e.message);}}
root.addEventListener('submit',async event=>{const form=event.target.closest('[data-form]');if(!form)return;event.preventDefault();
 if(form.dataset.form==='login'){const error=root.querySelector('#login-error');if(error)error.textContent='';try{const response=await request('login',{email:form.elements.namedItem('email').value,password:form.elements.namedItem('password').value});csrf=response.csrf;sessionAdmin=response.admin;await loadCatalog();render();}catch(e){if(error)error.innerHTML=`<div class="alert">${esc(e.message)}</div>`;}return;}
 await saveForm(form.dataset.form,form);});
root.addEventListener('change',event=>{if(event.target.id==='image-upload')imageUpload(event.target.files[0]);});
root.addEventListener('input',event=>{if(event.target.id==='search-products'){search=event.target.value;const caret=event.target.selectionStart;const active=event.target; // Update only table, preserving focus during typing.
 const scroll=window.scrollY;render();const next=root.querySelector('#search-products');if(next){next.focus();try{next.setSelectionRange(caret,caret)}catch{}}window.scrollTo(0,scroll);}});
root.addEventListener('click',async event=>{const button=event.target.closest('[data-action]');if(!button)return;
 const a=button.dataset.action, id=button.dataset.id||'';
 if(a==='close-backdrop'&&event.target!==button)return;
 if(a==='close-editor'||a==='close-backdrop'){editing=null;render();return;}
 if(a==='tab'){tab=button.dataset.tab;editing=null;search='';render();return;}
 if(a==='new-category'||a==='edit-category'){edit('category',id);return;}
 if(a==='new-product'||a==='edit-product'){edit('product',id);return;}
 if(a==='reload'){try{await loadCatalog();render();toast('فهرست به‌روز شد.')}catch(e){toast(e.message)}return;}
 if(a==='open-shop'){window.location.assign('/');return;}
 if(a==='logout'){try{await request('logout',{});sessionAdmin=null;csrf='';editing=null;await boot();}catch(e){toast(e.message)}return;}
 if(a==='delete-product'||a==='delete-category'){
  const kind=a==='delete-product'?'product':'category';
  if(!confirm(`آیا ${kind==='product'?'این محصول':'این دسته'} حذف شود؟ این عملیات قابل برگشت نیست.`))return;
  try{await request(kind+'.delete',{id});await loadCatalog();render();toast('حذف شد.');}catch(e){toast(e.message)}return;
 }
});
boot();
