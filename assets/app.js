(function () {
  "use strict";

  // Filter dropdown applies straight away.
  document.querySelectorAll("[data-autosubmit]").forEach(function (el) {
    el.addEventListener("change", function () { el.form.submit(); });
  });

  // Show the chosen photo before it is uploaded.
  document.querySelectorAll("input[type=file][data-preview]").forEach(function (input) {
    input.addEventListener("change", function () {
      var box = document.getElementById(input.dataset.preview);
      var file = input.files && input.files[0];
      if (!box || !file) return;
      var img = document.createElement("img");
      img.alt = "Photo preview";
      img.src = URL.createObjectURL(file);
      box.textContent = "";
      box.appendChild(img);
    });
  });

  // A confirmation box unlocks a dangerous button.
  document.querySelectorAll("input[type=checkbox][data-enables]").forEach(function (box) {
    var btn = document.getElementById(box.dataset.enables);
    if (!btn) return;
    box.addEventListener("change", function () { btn.disabled = !box.checked; });
  });
})();
