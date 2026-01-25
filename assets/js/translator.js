+function ($) { "use strict";

    var AiTranslator = function () {
        this.init();
    }

    AiTranslator.prototype.init = function () {
        $(document).on('click', '[data-ajax-handler="onInitAiTranslation"]', this.onClickTranslate.bind(this));
    }

    AiTranslator.prototype.onClickTranslate = function (e) {
        e.preventDefault();
        var $el = $(e.currentTarget);

        $el.request('onInitAiTranslation', {
            loading: $.oc.stripeLoadIndicator,
            success: this.onTranslationSuccess.bind(this, $el),
            error: this.onTranslationError.bind(this)
        });
    }

    AiTranslator.prototype.onTranslationSuccess = function ($el, data) {
        if (data.result) {
            $el.popup({
                content: data.result,
                size: 'huge' // 'small', 'large', 'huge', or 'giant'
            });

            $('[data-control="richeditor"]:not([data-richeditor-vue])').richEditor();
        }
    }

    AiTranslator.prototype.onTranslationError = function(event, context, data, status, jqXHR) {
        $.oc.flashMsg({ text: 'Translation Failed: ' + (context.errorMsg || jqXHR.responseText), class: 'error' });
    }

    $(document).ready(function() {
        new AiTranslator();

    })

}(window.jQuery);
