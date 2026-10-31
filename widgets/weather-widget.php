<?php
/**
 * Widget Meteo con OpenWeather API
 */

class OpenComune_Weather_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'opencomune_weather_widget',
            'Meteo OpenComune',
            array('description' => 'Widget per mostrare le condizioni meteo utilizzando OpenWeather API')
        );
    }
    
    public function widget($args, $instance) {
        $title = apply_filters('widget_title', $instance['title']);
        $city = !empty($instance['city']) ? $instance['city'] : '3175860'; // ID Gravina in Puglia
        $api_key = get_option('opencomune_openweather_api_key', '');
        
        if (empty($api_key)) {
            echo '<div class="weather-widget p-4 bg-blue-50 rounded-lg">';
            echo '<h4 class="font-bold text-blue-800 mb-2">Configurazione Meteo</h4>';
            echo '<p class="text-sm text-blue-600">Inserisci la chiave API di OpenWeather nelle impostazioni del tema.</p>';
            echo '<p class="text-xs text-blue-500 mt-2"><a href="https://openweathermap.org/api" target="_blank">Ottieni la chiave gratuita qui</a></p>';
            echo '</div>';
            return;
        }
        
        // Cache per 30 minuti
        $cache_key = 'weather_' . md5($city . $api_key);
        $weather_data = get_transient($cache_key);
        
        echo $args['before_widget'];
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        
        if ($weather_data && !is_wp_error($weather_data)) {
            $this->display_weather($weather_data);
        } else {
            // Se non ci sono dati in cache, prova a ottenerli
            $weather_data = $this->get_weather_data($city, $api_key);
            if ($weather_data && !is_wp_error($weather_data)) {
                set_transient($cache_key, $weather_data, 30 * MINUTE_IN_SECONDS);
                $this->display_weather($weather_data);
            } else {
                $this->display_error();
            }
        }
        
        echo $args['after_widget'];
    }
    
    private function get_weather_data($city, $api_key) {
        // Se è un ID numerico, usa il parametro id, altrimenti usa q per nome città
        if (is_numeric($city)) {
            $url = "https://api.openweathermap.org/data/2.5/weather?id=" . $city . "&appid=" . $api_key . "&units=metric&lang=it";
        } else {
            $url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . "&appid=" . $api_key . "&units=metric&lang=it";
        }
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'OpenComune WordPress Theme'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        // Verifica se l'API ha restituito un errore
        if (isset($data['cod']) && $data['cod'] !== 200) {
            // Log dell'errore per debug
            error_log('OpenWeather API Error: ' . json_encode($data));
            
            // Gestione specifica dell'errore 401
            if ($data['cod'] === 401) {
                error_log('OpenWeather API 401 Error - Chiave API non valida o non attivata');
            }
            
            return false;
        }
        
        return $data;
    }
    
    private function display_weather($data) {
        // Verifica che i dati siano validi
        if (!isset($data['main']) || !isset($data['weather']) || !isset($data['weather'][0]) || !isset($data['name'])) {
            $this->display_error();
            return;
        }
        
        $temp = isset($data['main']['temp']) ? round($data['main']['temp']) : 0;
        $description = isset($data['weather'][0]['description']) ? ucfirst($data['weather'][0]['description']) : 'N/A';
        $icon = isset($data['weather'][0]['icon']) ? $data['weather'][0]['icon'] : '01d';
        $humidity = isset($data['main']['humidity']) ? $data['main']['humidity'] : 0;
        $wind_speed = isset($data['wind']['speed']) ? round($data['wind']['speed'] * 3.6) : 0; // Converti da m/s a km/h
        $city_name = isset($data['name']) ? $data['name'] : 'Città';
        
        echo '<div class="weather-widget bg-gradient-to-br from-blue-500 to-blue-600 text-white p-4 rounded-lg shadow-lg">';
        echo '<div class="flex items-center justify-between mb-3">';
        echo '<div>';
        echo '<h4 class="font-bold text-lg">' . esc_html($city_name) . '</h4>';
        echo '<p class="text-blue-100 text-sm">' . esc_html($description) . '</p>';
        echo '</div>';
        echo '<div class="text-right">';
        echo '<div class="flex items-center">';
        echo '<img src="https://openweathermap.org/img/wn/' . esc_attr($icon) . '@2x.png" alt="' . esc_attr($description) . '" class="w-12 h-12">';
        echo '<span class="text-3xl font-bold ml-2">' . $temp . '°C</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="grid grid-cols-2 gap-3 mt-3 text-sm">';
        echo '<div class="flex items-center">';
        echo '<i class="bi bi-droplet text-blue-200 mr-2"></i>';
        echo '<span>Umidità: ' . $humidity . '%</span>';
        echo '</div>';
        echo '<div class="flex items-center">';
        echo '<i class="bi bi-wind text-blue-200 mr-2"></i>';
        echo '<span>Vento: ' . $wind_speed . ' km/h</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="mt-3 text-xs text-blue-200 text-center">';
        echo 'Dati aggiornati ogni 30 minuti';
        echo '</div>';
        echo '</div>';
    }
    
    private function display_error() {
        echo '<div class="weather-widget p-4 bg-red-50 border border-red-200 rounded-lg">';
        echo '<h4 class="font-bold text-red-800 mb-2">Errore Meteo</h4>';
        echo '<p class="text-sm text-red-600">Impossibile caricare i dati meteo. Riprova più tardi.</p>';
        echo '<p class="text-xs text-red-500 mt-2">Se il problema persiste, verifica la configurazione nelle impostazioni del tema.</p>';
        echo '</div>';
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Meteo';
        $city = !empty($instance['city']) ? $instance['city'] : '3175860';
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Titolo:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('city'); ?>">ID Città OpenWeather:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('city'); ?>" name="<?php echo $this->get_field_name('city'); ?>" type="text" value="<?php echo esc_attr($city); ?>" placeholder="3175860">
            <small class="description">ID: 3175860 = Gravina in Puglia</small>
        </p>
        <p class="description">
            <strong>Nota:</strong> Assicurati di aver configurato la chiave API di OpenWeather nelle impostazioni del tema.
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['city'] = (!empty($new_instance['city'])) ? strip_tags($new_instance['city']) : '3175860';
        return $instance;
    }
}
