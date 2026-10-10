<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CS_Report {

	public static function table( $report ) {
		if ( empty( $report['findings'] ) ) {
			return '<p>Aucun audit disponible pour le moment.</p>';
		}
		$cats   = CS_Audit::categories();
		$labels = array( 'pass' => 'OK', 'warn' => 'À surveiller', 'fail' => 'À corriger' );
		$rows   = '';
		foreach ( $report['findings'] as $f ) {
			$cat   = isset( $cats[ $f['category'] ] ) ? $cats[ $f['category'] ] : $f['category'];
			$badge = isset( $labels[ $f['status'] ] ) ? $labels[ $f['status'] ] : $f['status'];
			$rows .= "<tr class='cloux-seo-row-" . esc_attr( $f['status'] ) . "'>";
			$rows .= "<td><span class='cloux-seo-badge cloux-seo-badge-" . esc_attr( $f['status'] ) . "'>" . esc_html( $badge ) . '</span></td>';
			$rows .= '<td><strong>' . esc_html( $f['label'] ) . '</strong><br><small>' . esc_html( $cat ) . ' — priorité ' . esc_html( $f['severity'] ) . '</small></td>';
			$rows .= '<td>' . esc_html( $f['finding'] ) . '</td>';
			$rows .= '<td>' . esc_html( $f['recommendation'] ) . '</td>';
			$rows .= '<td>' . esc_html( $f['verify'] ) . '</td>';
			$rows .= '</tr>';
		}
		return "<table class='cloux-seo-table widefat striped'><thead><tr><th>Statut</th><th>Contrôle</th><th>Constat</th><th>Recommandation</th><th>Vérification</th></tr></thead><tbody>" . $rows . '</tbody></table>';
	}

	public static function csv( $report ) {
		if ( empty( $report['findings'] ) ) {
			return '';
		}
		$out = 'domaine;date;score;controle;categorie;statut;poids;constat;recommandation;verification' . PHP_EOL;
		foreach ( $report['findings'] as $f ) {
			$out .= $report['site'] . ';' . $report['date'] . ';' . $report['score'] . ';' . $f['label'] . ';' . $f['category'] . ';' . $f['status'] . ';' . $f['weight'] . ';' . self::cell( $f['finding'] ) . ';' . self::cell( $f['recommendation'] ) . ';' . self::cell( $f['verify'] ) . PHP_EOL;
		}
		return $out;
	}

	private static function cell( $value ) {
		return '"' . str_replace( '"', '""', (string) $value ) . '"';
	}

	public static function json( $report ) {
		return (string) wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
