<?php
/*
Template Name: Partner Locali
*/
get_header();
?>

<main class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-4xl font-bold text-center mb-8">Partner Locali</h1>
        
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
            <h2 class="text-2xl font-semibold mb-4">Collabora con noi</h2>
            <p class="text-gray-700 mb-6">
                Siamo sempre alla ricerca di partner locali qualificati per arricchire la nostra offerta di esperienze turistiche. 
                Se sei una guida turistica, un operatore locale o un'azienda del territorio, contattaci per diventare nostro partner.
            </p>
            
            <div class="grid md:grid-cols-2 gap-6 mb-8">
                <div class="bg-blue-50 p-6 rounded-lg">
                    <h3 class="text-xl font-semibold mb-3 text-blue-800">Vantaggi per i Partner</h3>
                    <ul class="space-y-2 text-gray-700">
                        <li>• Visibilità sulla nostra piattaforma</li>
                        <li>• Accesso a una clientela qualificata</li>
                        <li>• Supporto nella promozione delle esperienze</li>
                        <li>• Gestione semplificata delle prenotazioni</li>
                    </ul>
                </div>
                
                <div class="bg-green-50 p-6 rounded-lg">
                    <h3 class="text-xl font-semibold mb-3 text-green-800">Requisiti</h3>
                    <ul class="space-y-2 text-gray-700">
                        <li>• Patentino guida turistica valido</li>
                        <li>• Esperienza nel settore turistico</li>
                        <li>• Conoscenza del territorio locale</li>
                        <li>• Disponibilità per collaborazioni</li>
                    </ul>
                </div>
            </div>
            
            <div class="text-center">
                <h3 class="text-xl font-semibold mb-4">Contattaci</h3>
                <p class="text-gray-700 mb-4">
                    Per maggiori informazioni su come diventare nostro partner, contattaci:
                </p>
                <div class="flex flex-col md:flex-row gap-4 justify-center">
                    <a href="mailto:info@opencomune.com" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="bi bi-envelope mr-2"></i>info@opencomune.com
                    </a>
                    <a href="tel:+390123456789" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="bi bi-telephone mr-2"></i>+39 012 345 6789
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Lista Partner Attivi -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h2 class="text-2xl font-semibold mb-6">I nostri Partner</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php
                $partners = get_posts([
                    'post_type' => 'partner',
                    'post_status' => 'publish',
                    'posts_per_page' => 6
                ]);
                
                if ($partners):
                    foreach ($partners as $partner):
                        $partner_img = get_the_post_thumbnail_url($partner->ID, 'medium');
                        $specializzazioni = get_post_meta($partner->ID, '_specializzazioni', true);
                        $citta = get_post_meta($partner->ID, '_citta', true);
                ?>
                <div class="bg-gray-50 rounded-lg p-6 text-center">
                    <?php if ($partner_img): ?>
                        <img src="<?php echo esc_url($partner_img); ?>" alt="<?php echo esc_attr($partner->post_title); ?>" class="w-20 h-20 rounded-full mx-auto mb-4 object-cover">
                    <?php else: ?>
                        <div class="w-20 h-20 bg-blue-600 text-white rounded-full mx-auto mb-4 flex items-center justify-center text-2xl font-bold">
                            <?php echo strtoupper(substr($partner->post_title, 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3 class="font-semibold text-lg mb-2"><?php echo esc_html($partner->post_title); ?></h3>
                    
                    <?php if ($specializzazioni): ?>
                        <p class="text-sm text-gray-600 mb-2">
                            <i class="bi bi-star mr-1"></i><?php echo esc_html($specializzazioni); ?>
                        </p>
    <?php endif; ?>

                    <?php if ($citta): ?>
                        <p class="text-sm text-gray-500">
                            <i class="bi bi-geo-alt mr-1"></i><?php echo esc_html($citta); ?>
                        </p>
        <?php endif; ?>
                </div>
                <?php
                    endforeach;
                else:
                ?>
                <div class="col-span-full text-center py-8">
                    <p class="text-gray-500">Al momento non ci sono partner attivi. Contattaci per diventare il primo!</p>
                </div>
                <?php endif; ?>
            </div>
            </div>
    </div>
</main>

<?php get_footer(); ?>