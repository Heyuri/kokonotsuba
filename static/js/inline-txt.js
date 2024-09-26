/* LOL HEYURI
 */

/* Module */
const kkinline = { name: "KK Quote Inlining",
	startup: function () {
		if (!localStorage.getItem("quoteinline")) {
			localStorage.setItem("quoteinline", "false");
		}
		if (localStorage.getItem("quoteinline") != "true") {
			return true;
		}
		document.querySelectorAll(".quotelink").forEach(function (i) {
			i.onclick = function(e){
				if (e.shiftKey) {
					window.location.href = this.href;
					return false;
				}
				if (this.nextElementSibling) {
					if (this.nextElementSibling.classList.contains("inline-quote")) {
						this.nextElementSibling.remove();
						return false;
					}
				}
				var o = document.querySelector("#p"+this.innerText.slice(2));
				if (!o) {return true;}
				var t = document.createElement("div");
				t.classList.add("inline-quote");
				this.insertAdjacentElement("afterEnd",t);
				t.style.border = "1px dashed";
				t.style.borderColor = window.getComputedStyle(document.querySelector(".reply"),null).getPropertyValue("border-color");
				t.innerHTML = o.outerHTML;
				if (t.querySelector(".op")) {
					t.querySelector(".op").id = o.id + "-inline"+this.parentElement.parentElement.id;
				} else {
					t.id = o.id + "-inline"+this.parentElement.parentElement.id;
				}
				if (this.parentElement.classList.contains("comment")) {
					kkinline.startup();
				}
				return false;
			}
		});
		return true;
	},
	reset: function () {
		document.querySelectorAll(".inline-quote").forEach(function (i) {
			i.remove();
		});
		document.querySelectorAll(".quotelink").forEach(function (i) {
			i.onclick = function(){}
		});
	},
	sett: function (tab, div) { if (tab!="general") return;
		div.innerHTML+= `
			<label><input type="checkbox" onchange="localStorage.setItem('quoteinline',this.checked);kkinline.reset();kkinline.startup();"`+(localStorage.getItem("quoteinline")=="true"?'checked="checked"':'')+`>Quote inlining</label>
			`;
	}
};

/* Register */
if(typeof(KOKOJS)!="undefined"){kkjs.modules.push(kkinline);}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
