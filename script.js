document.addEventListener("DOMContentLoaded",()=>{
 const search=document.querySelector("#searchInput");
 if(search) search.addEventListener("input",()=>{const q=search.value.toLowerCase().trim();document.querySelectorAll(".resource-card[data-search]").forEach(c=>c.style.display=c.dataset.search.includes(q)?"":"none")});
 document.querySelectorAll("[data-password-toggle]").forEach(b=>b.addEventListener("click",()=>{const i=document.getElementById(b.dataset.passwordToggle);i.type=i.type==="password"?"text":"password";b.textContent=i.type==="password"?"Show":"Hide"}));
 const f=document.querySelector("#resourceFile"), n=document.querySelector("#fileName");
 if(f&&n)f.addEventListener("change",()=>n.textContent=f.files.length?`${f.files[0].name} • ${(f.files[0].size/1024/1024).toFixed(2)} MB`:"No file selected");
 const reg=document.querySelector("#regForm"),p=document.querySelector("#password"),c=document.querySelector("#confirm_password");
 if(reg&&p&&c)reg.addEventListener("submit",e=>{if(p.value!==c.value){e.preventDefault();const b=document.querySelector("#reg-error");b.className="alert alert-danger";b.textContent="Passwords do not match.";b.style.display="block"}});
 document.querySelectorAll("form[data-loading]").forEach(x=>x.addEventListener("submit",()=>{const b=x.querySelector("button[type=submit]");if(b){b.disabled=true;b.textContent="Please wait..."}}));
 const footer=document.querySelector(".site-footer")||document.body.appendChild(document.createElement("footer"));
 footer.className="site-footer";
 footer.innerHTML='<div class="container footer-grid"><div><strong class="footer-brand">Student hub ug</strong><p>Learn, share and connect with your student community.</p></div><div><strong>Contact us</strong><a href="mailto:rahidkagere787@gmail.com">rahidkagere787@gmail.com</a><a href="https://wa.me/256761480601" target="_blank" rel="noopener">WhatsApp: +256 761 480601</a></div><div><strong>Quick links</strong><a href="index.html">Home</a><a href="resources.php">Resources</a><a href="tutoring.php">Tutoring</a></div></div><div class="container footer-bottom"><span>© 2026 Student hub ug. All rights reserved.</span><span>Learn • Share • Connect</span></div>';
});
