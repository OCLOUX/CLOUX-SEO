(function ($) {
	'use strict';

	function post(data) {
		return $.post(clouxSeo.ajaxUrl, data);
	}

	$(function () {
		$('#cloux-seo-run').on('click', function (e) {
			e.preventDefault();
			var $b = $(this);
			$b.prop('disabled', true).text('Audit en cours…');
			post({ action: 'cloux_seo_run_audit', nonce: clouxSeo.nonce }).done(function () {
				window.location.reload();
			}).fail(function () {
				$b.prop('disabled', false).text('Lancer l’audit');
			});
		});

		$('#cloux-seo-add').on('click', function (e) {
			e.preventDefault();
			var url = $('#cloux-seo-domain').val().trim();
			if (!url) {
				return;
			}
			post({ action: 'cloux_seo_remote', nonce: clouxSeo.nonce, do: 'add', domain: url }).done(function () {
				window.location.reload();
			});
		});

		$(document).on('click', '.cloux-seo-audit', function (e) {
			e.preventDefault();
			var $b = $(this).prop('disabled', true).text('Audit…');
			post({ action: 'cloux_seo_remote', nonce: clouxSeo.nonce, do: 'audit', domain: $b.data('domain') }).done(function (res) {
				if (res && res.data) {
					var $tr = $b.closest('tr');
					$tr.find('.cloux-seo-remote-score').text(res.data.score + '/100');
					$tr.find('.cloux-seo-remote-date').text(res.data.date);
				}
				$b.prop('disabled', false).text('Auditer');
			}).fail(function () {
				$b.prop('disabled', false).text('Auditer');
			});
		});

		$(document).on('click', '.cloux-seo-remove', function (e) {
			e.preventDefault();
			if (!window.confirm('Supprimer ce domaine ?')) {
				return;
			}
			post({ action: 'cloux_seo_remote', nonce: clouxSeo.nonce, do: 'remove', domain: $(this).data('domain') }).done(function () {
				window.location.reload();
			});
		});
	});
})(jQuery);
