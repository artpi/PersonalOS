<?php
// Just a placeholder for now
require_once __DIR__ . '/vendor/autoload.php';

$doc_tabs = [];
foreach ( POS::$modules as $module ) {
	$readme = $module->get_readme();
	if ( $readme ) {
		$doc_tabs[] = [
			'id' => $module->id,
			'name' => $module->name,
			'readme' => $readme,
		];
	}
}
$module_id = isset( $_GET['module'] ) ? sanitize_text_field( $_GET['module'] ) : 'notes';
$show_readme = '';
?>
<style>
	#pos-app ul {
		list-style: disc;
		padding: 1rem;
	}
</style>
<div id="pos-app"><h1>PersonalOS!</h1>
<p>A personal operating system for managing your life.</p>
<h2 class="nav-tab-wrapper">
	<?php
	foreach ( $doc_tabs as $tab ) {
		$active = $tab['id'] === $module_id;
		echo '<a class="nav-tab ' . ( $active ? 'nav-tab-active' : '' ) . '" href="?page=personalos-settings&module=' . esc_attr( $tab['id'] ) . '">' . esc_html( $tab['name'] ) . '</a>';
		if ( $active ) {
			$show_readme = Parsedown::instance()->text( $tab['readme'] );
		}
	}
	?>
</h2>
<div class="tabs-content">
	<?php echo wp_kses_post( $show_readme ); ?>
</div>
</div>
