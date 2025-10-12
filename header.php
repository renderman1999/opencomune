<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">

		<link rel="profile" href="https://gmpg.org/xfn/11">

		<?php wp_head(); ?>
    <title><?php wp_title('|', true, 'right'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
 </head>
<body <?php body_class('bg-gray-50'); ?>>
<?php
		wp_body_open();
		?>
    <header class="bg-white shadow">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <!-- Logo circolare con icona -->
            <a href="<?php echo home_url('/'); ?>">
                <?php if (function_exists('the_custom_logo') && has_custom_logo()): ?>
                    <?php
                    $custom_logo_id = get_theme_mod('custom_logo');
                    $logo = wp_get_attachment_image($custom_logo_id, 'full', false, array('class' => 'logo_header'));
                    echo $logo;
                    ?>
                <?php else: ?>
                    <span class="text-2xl font-bold text-gray-900"><?php bloginfo('name'); ?></span>
                <?php endif; ?>
                </a>
            </div>
            
            <!-- Hamburger per mobile -->
            <button id="mobile-menu-btn" class="md:hidden block text-3xl text-gray-700 focus:outline-none" aria-label="Apri menu">
                <i class="bi bi-list"></i>
            </button>
            
            <!-- Menu desktop -->
            <nav id="desktop-menu" class="hidden md:flex space-x-4 font-light text-xl">
                <?php
                wp_nav_menu([
                    'theme_location' => 'header',
                    'container' => false,
                    'menu_class' => 'flex space-x-4 font-light text-xl',
                    'fallback_cb' => false
                ]);
                ?>
            </nav>
            
            <div class="space-x-2 hidden md:block">
                <?php if (is_user_logged_in() && current_user_can('editor_turistico')): 
                    $user = wp_get_current_user();
                    $initials = '';
                    if ($user->first_name && $user->last_name) {
                        $initials = strtoupper(mb_substr($user->first_name,0,1) . mb_substr($user->last_name,0,1));
                    } else {
                        $initials = strtoupper(mb_substr($user->display_name,0,2));
                    }
                ?>
                <div class="relative inline-block text-left">
                    <button id="user-menu-btn" type="button" class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-600 text-white font-bold focus:outline-none">
                        <?php echo esc_html($initials); ?>
                    </button>
                    <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded shadow-lg z-50">
                        <a href="<?php echo home_url('/dashboard-ufficio/'); ?>" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Dashboard</a>
                        <a href="<?php echo wp_logout_url(home_url('/')); ?>" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Esci</a>
                    </div>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var btn = document.getElementById('user-menu-btn');
                    var menu = document.getElementById('user-menu-dropdown');
                    if(btn && menu) {
                        btn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            menu.classList.toggle('hidden');
                        });
                        document.addEventListener('click', function() {
                            menu.classList.add('hidden');
                        });
                    }
                });
                </script>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Overlay menu mobile -->
    <div id="mobile-menu-overlay" class="fixed inset-0 bg-white bg-opacity-95 z-50 flex flex-col items-center justify-center hidden">
        <button id="mobile-menu-close" class="absolute top-6 right-6 text-4xl text-gray-700" aria-label="Chiudi menu">
            <i class="bi bi-x"></i>
        </button>
        <nav class="w-full flex flex-col items-center gap-8 mt-16">
            <?php
            wp_nav_menu([
                'theme_location' => 'header',
                'container' => false,
                'menu_class' => 'flex flex-col items-center gap-8 text-2xl',
                'fallback_cb' => false
            ]);
            ?>
            <div class="mt-8">
                <?php if (is_user_logged_in() && current_user_can('editor_turistico')): ?>
                    <a href="<?php echo home_url('/dashboard-ufficio/'); ?>" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Dashboard</a>
                    <a href="<?php echo wp_logout_url(home_url('/')); ?>" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Esci</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
    <style>
    @media (max-width: 900px) {
        #desktop-menu, .space-x-2.md\:block { display: none !important; }
        #mobile-menu-btn { display: block !important; }
    }
    @media (min-width: 901px) {
        #mobile-menu-btn, #mobile-menu-overlay { display: none !important; }
        #desktop-menu, .space-x-2.md\:block { display: flex !important; }
    }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var mobileMenuBtn = document.getElementById('mobile-menu-btn');
        var mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
        var mobileMenuClose = document.getElementById('mobile-menu-close');
        if(mobileMenuBtn && mobileMenuOverlay && mobileMenuClose) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenuOverlay.classList.remove('hidden');
            });
            mobileMenuClose.addEventListener('click', function() {
                mobileMenuOverlay.classList.add('hidden');
            });
            // Chiudi overlay cliccando fuori dal menu
            mobileMenuOverlay.addEventListener('click', function(e) {
                if(e.target === mobileMenuOverlay) mobileMenuOverlay.classList.add('hidden');
            });
        }
    });
    </script>
 


 

