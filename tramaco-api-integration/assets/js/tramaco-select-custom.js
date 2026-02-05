/**
 * TRAMACO - Select Personalizado
 * Convierte los selects nativos en componentes personalizados
 */

(function ($) {
  "use strict";

  class TramacoCustomSelect {
    constructor(selectElement) {
      this.select = $(selectElement);
      this.wrapper = null;
      this.customSelect = null;
      this.trigger = null;
      this.textSpan = null;
      this.options = null;

      this.init();
    }

    init() {
      // Convertir selects con las clases específicas de Tramaco
      const hasValidClass =
        this.select.hasClass("form-select") ||
        this.select.hasClass("form-control-select") ||
        (this.select.hasClass("form-control") && this.select.is("select"));

      if (!hasValidClass) {
        return;
      }

      this.buildCustomSelect();
      this.bindEvents();
      this.updateText();
    }

    buildCustomSelect() {
      const selectId = this.select.attr("id") || "";
      const selectName = this.select.attr("name") || "";
      const selectClass = this.select.attr("class") || "";

      // Crear estructura del select personalizado
      this.wrapper = $("<div>", {
        class: "tramaco-custom-select-wrapper",
      });

      this.customSelect = $("<div>", {
        class: "tramaco-custom-select",
        "data-select-id": selectId,
      });

      // Trigger (botón del select)
      this.trigger = $("<div>", {
        class: "tramaco-custom-select-trigger",
      });

      this.textSpan = $("<span>", {
        class: "tramaco-custom-select-text placeholder",
      });

      const arrow = $("<div>", {
        class: "tramaco-custom-select-arrow",
        html: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>',
      });

      this.trigger.append(this.textSpan).append(arrow);

      // Lista de opciones
      this.options = $("<div>", {
        class: "tramaco-custom-select-options",
      });

      // Agregar opciones desde el select original
      this.select.find("option").each((index, option) => {
        const $option = $(option);
        const text = $option.text().trim();
        const value = $option.val();

        const optionDiv = $("<div>", {
          class: "tramaco-custom-select-option",
          "data-value": value,
          text: text,
        });

        if ($option.is(":selected")) {
          optionDiv.addClass("selected");
        }

        this.options.append(optionDiv);
      });

      // Ensamblar
      this.customSelect.append(this.trigger).append(this.options);
      this.wrapper.append(this.customSelect);

      // Ocultar select original y agregar el personalizado
      this.select.hide().after(this.wrapper);
    }

    bindEvents() {
      const self = this;

      // Click en el trigger
      this.trigger.on("click", function (e) {
        e.stopPropagation();
        self.toggle();
      });

      // Click en una opción
      this.options.on("click", ".tramaco-custom-select-option", function (e) {
        e.stopPropagation();
        const value = $(this).attr("data-value");
        self.selectOption(value);
      });

      // Cerrar al hacer click fuera
      $(document).on("click", function (e) {
        if (!$(e.target).closest(self.customSelect).length) {
          self.close();
        }
      });

      // Reposicionar en resize y cerrar en scroll
      $(window).on("resize", function () {
        if (self.customSelect.hasClass("open")) {
          self.open(); // Recalcular posición
        }
      });

      $(window).on("scroll", function () {
        if (self.customSelect.hasClass("open")) {
          self.close(); // Cerrar en scroll para evitar posición incorrecta
        }
      });

      // Sincronizar con cambios en el select original
      this.select.on("change", function () {
        self.updateText();
        self.updateSelectedOption();
      });
    }

    toggle() {
      if (this.customSelect.hasClass("open")) {
        this.close();
      } else {
        this.open();
      }
    }

    open() {
      // Cerrar otros selects abiertos
      $(".tramaco-custom-select.open")
        .not(this.customSelect)
        .removeClass("open");

      // Calcular posición del dropdown (usando position fixed)
      const triggerRect = this.trigger[0].getBoundingClientRect();
      const viewportHeight = window.innerHeight;
      const dropdownHeight = Math.min(280, this.options[0].scrollHeight);

      // Determinar si abrir hacia arriba o abajo
      const spaceBelow = viewportHeight - triggerRect.bottom;
      const spaceAbove = triggerRect.top;
      const openUpward = spaceBelow < dropdownHeight && spaceAbove > spaceBelow;

      // Posicionar el dropdown
      this.options.css({
        left: triggerRect.left + "px",
        width: triggerRect.width + "px",
        "max-width": triggerRect.width + "px",
      });

      if (openUpward) {
        // Abrir hacia arriba
        this.options.css({
          top: "auto",
          bottom: viewportHeight - triggerRect.top + 4 + "px",
        });
        this.options.addClass("open-upward");
      } else {
        // Abrir hacia abajo
        this.options.css({
          top: triggerRect.bottom + 4 + "px",
          bottom: "auto",
        });
        this.options.removeClass("open-upward");
      }

      this.customSelect.addClass("open");
    }

    close() {
      this.customSelect.removeClass("open");
    }

    selectOption(value) {
      // Actualizar select original
      this.select.val(value).trigger("change");

      // Actualizar opciones seleccionadas
      this.options
        .find(".tramaco-custom-select-option")
        .removeClass("selected");
      this.options.find('[data-value="' + value + '"]').addClass("selected");

      // Actualizar texto
      this.updateText();

      // Cerrar
      this.close();
    }

    updateText() {
      const selectedOption = this.select.find("option:selected");
      const text = selectedOption.text().trim();

      if (text && selectedOption.val()) {
        this.textSpan.text(text).removeClass("placeholder");
      } else {
        this.textSpan
          .text(this.select.find("option:first").text())
          .addClass("placeholder");
      }
    }

    updateSelectedOption() {
      const selectedValue = this.select.val();
      this.options
        .find(".tramaco-custom-select-option")
        .removeClass("selected");
      this.options
        .find('[data-value="' + selectedValue + '"]')
        .addClass("selected");
    }

    refresh() {
      // Reconstruir opciones
      this.options.empty();

      this.select.find("option").each((index, option) => {
        const $option = $(option);
        const text = $option.text().trim();
        const value = $option.val();

        const optionDiv = $("<div>", {
          class: "tramaco-custom-select-option",
          "data-value": value,
          text: text,
        });

        if ($option.is(":selected")) {
          optionDiv.addClass("selected");
        }

        this.options.append(optionDiv);
      });

      this.updateText();
    }

    destroy() {
      this.wrapper.remove();
      this.select.show();
    }
  }

  // Plugin jQuery
  $.fn.tramacoCustomSelect = function () {
    return this.each(function () {
      if (!$(this).data("tramaco-custom-select")) {
        const customSelect = new TramacoCustomSelect(this);
        $(this).data("tramaco-custom-select", customSelect);
      }
    });
  };

  // Inicializar automáticamente cuando el documento esté listo
  $(document).ready(function () {
    // Convertir todos los selects de los formularios de Tramaco
    initializeCustomSelects();
  });

  // Función para inicializar selects
  function initializeCustomSelects() {
    $(".form-select, .form-control-select, select.form-control").each(
      function () {
        if (!$(this).data("tramaco-custom-select")) {
          $(this).tramacoCustomSelect();
        }
      }
    );
  }

  // Reinicializar después de cambios dinámicos (por si se agregan nuevos selects)
  $(document).on("DOMNodeInserted", function (e) {
    if (
      $(e.target).is("select") &&
      ($(e.target).hasClass("form-select") ||
        $(e.target).hasClass("form-control-select") ||
        $(e.target).hasClass("form-control"))
    ) {
      setTimeout(function () {
        if (!$(e.target).data("tramaco-custom-select")) {
          $(e.target).tramacoCustomSelect();
        }
      }, 100);
    }
  });

  // Exponer la clase globalmente
  window.TramacoCustomSelect = TramacoCustomSelect;
  window.initializeCustomSelects = initializeCustomSelects;
})(jQuery);
