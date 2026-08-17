document.addEventListener("DOMContentLoaded",()=>{
 const search=document.querySelector("#searchInput");
 if(search) search.addEventListener("input",()=>{const q=search.value.toLowerCase().trim();document.querySelectorAll(".resource-card[data-search]").forEach(c=>c.style.display=c.dataset.search.includes(q)?"":"none")});
 document.querySelectorAll("[data-password-toggle]").forEach(b=>b.addEventListener("click",()=>{const i=document.getElementById(b.dataset.passwordToggle);i.type=i.type==="password"?"text":"password";b.textContent=i.type==="password"?"Show":"Hide"}));
 const f=document.querySelector("#resourceFile"), n=document.querySelector("#fileName");
 if(f&&n)f.addEventListener("change",()=>n.textContent=f.files.length?`${f.files[0].name} • ${(f.files[0].size/1024/1024).toFixed(2)} MB`:"No file selected");
 const reg=document.querySelector("#regForm"),p=document.querySelector("#password"),c=document.querySelector("#confirm_password");
 if(reg&&p&&c)reg.addEventListener("submit",e=>{if(p.value!==c.value){e.preventDefault();const b=document.querySelector("#reg-error");b.className="alert alert-danger";b.textContent="Passwords do not match.";b.style.display="block"}});
 document.querySelectorAll("form[data-loading]").forEach(x=>x.addEventListener("submit",()=>{const b=x.querySelector("button[type=submit]");if(b){b.disabled=true;b.textContent="Please wait..."}}));
});
