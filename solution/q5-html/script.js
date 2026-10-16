(function () {
    'use strict';

    var container   = document.querySelector('article');
    var addBtn      = document.getElementById('add-paragraphs');
    var originals   = Array.from(document.querySelectorAll('article > p'));

    var toggleBtn = document.createElement('button');
    toggleBtn.type        = 'button';
    toggleBtn.id          = 'toggle-image-paragraphs';
    toggleBtn.textContent = 'Toggle image paragraphs';
    addBtn.insertAdjacentElement('afterend', toggleBtn);

    var imageParasHidden = false;

    function shuffled(arr) {
        var copy = arr.slice();
        for (var i = copy.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = copy[i];
            copy[i] = copy[j];
            copy[j] = tmp;
        }
        return copy;
    }

    function hasImage(p) {
        return p.querySelector('img') !== null;
    }

    function applyHiddenState(p) {
        if (imageParasHidden && hasImage(p)) {
            p.style.display = 'none';
        }
    }

    function rebuildSeparators() {
        var allParas = Array.from(document.querySelectorAll('article > p'));
        var hrs = Array.from(document.querySelectorAll('article > hr'));

        hrs.forEach(function (hr) { hr.remove(); });

        for (var i = 2; i < allParas.length; i += 2) {
            var hr = document.createElement('hr');
            allParas[i].insertAdjacentElement('beforebegin', hr);
        }
    }

    function addCopies() {
        var clones = shuffled(originals).map(function (p) {
            return p.cloneNode(true);
        });

        clones.forEach(function (clone) {
            applyHiddenState(clone);
            container.insertBefore(clone, addBtn);
        });

        rebuildSeparators();
    }

    toggleBtn.addEventListener('click', function () {
        imageParasHidden = !imageParasHidden;
        var allParas = Array.from(document.querySelectorAll('article > p'));
        allParas.forEach(function (p) {
            if (hasImage(p)) {
                p.style.display = imageParasHidden ? 'none' : '';
            }
        });
    });

    addBtn.addEventListener('click', addCopies);

    rebuildSeparators();
}());
