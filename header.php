<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">

		<link rel="profile" href="https://gmpg.org/xfn/11">

		<?php wp_head(); ?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
 </head>
<body <?php body_class('bg-gray-50 flex flex-col min-h-screen'); ?>>
<?php
		wp_body_open();

		$is_partner_dashboard = opencomune_is_partner_dashboard_page();
		$show_partner_header = $is_partner_dashboard && is_user_logged_in() && current_user_can('partner');
		$partner_welcome_name = $show_partner_header ? opencomune_get_partner_welcome_name() : '';

		if ($show_partner_header) :
			?>
    <header class="shadow" style="background-color: #004d99;">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="text-white">
                <p class="text-xl md:text-2xl font-semibold leading-tight">
                    Benvenuto<?php echo $partner_welcome_name !== '' ? ', ' . esc_html($partner_welcome_name) : ''; ?>
                </p>
            </div>
        </div>
    </header>
			<?php
		else :
			get_template_part('template-parts/public', 'site-header');
		endif;
		?>

