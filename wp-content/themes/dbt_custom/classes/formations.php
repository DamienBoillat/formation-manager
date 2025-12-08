<?php
/**
 * Classe Formation
 *
 * Représente une formation stockée comme un WP_Post de type "formations".
 */

class Formation
{
    /** @var WP_Post|null */
    protected $post;

    /* --- Champs métier --- */
    protected $duree;
    protected $formateur;
    protected $public_cible;
    protected $pilier;
    protected $places_disponibles;
    protected $min_inscriptions;

    /* --- Constantes --- */
    const POST_TYPE = 'formations';

    public function __construct( $post = null )
    {
        if ( $post instanceof WP_Post ) {
            $this->post = $post;
        } elseif ( is_numeric( $post ) ) {
            $this->post = get_post( (int) $post );
        } elseif ( $post === null ) {
            $this->post = null; // sera créé lors du save()
        }

        if ( $this->post ) {
            $this->load_meta();
        }
    }

    /*
     * Enregistre le Custom Post Type "formations".
     * Appeler ceci sur le hook "init".
     *
    public static function register_post_type()
    {
        $labels = [
            'name'               => 'Formations',
            'singular_name'      => 'Formation',
            'add_new'            => 'Ajouter',
            'add_new_item'       => 'Ajouter une formation',
            'edit_item'          => 'Modifier une formation',
            'new_item'           => 'Nouvelle formation',
            'view_item'          => 'Voir la formation',
            'search_items'       => 'Rechercher des formations',
            'not_found'          => 'Aucune formation trouvée',
            'not_found_in_trash' => 'Aucune formation trouvée dans la corbeille',
            'all_items'          => 'Toutes les formations',
            'menu_name'          => 'Formations',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => true,
            'show_in_rest'       => true, // support éditeur Gutenberg / REST
            'supports'           => [ 'title', 'editor', 'excerpt' ],
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-welcome-learn-more',
        ];

        register_post_type( self::POST_TYPE, $args );
    }*/

    /* ==================== CRUD STATIQUES ==================== */

    /**
     * Récupère une formation par ID.
     */
    public static function get( $id )
    {
        $post = get_post( (int) $id );

        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return null;
        }

        return new self( $post );
    }

    /**
     * Retourne une liste de Formation via WP_Query.
     *
     * @param array $args Arguments WP_Query supplémentaires
     * @return Formation[]
     */
    public static function query( $args = [] )
    {
        $defaults = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ];

        $q = new WP_Query( array_merge( $defaults, $args ) );
        $results = [];

        if ( $q->have_posts() ) {
            foreach ( $q->posts as $post ) {
                $results[] = new self( $post );
            }
        }

        return $results;
    }

    /**
     * Crée une nouvelle formation (non encore persistée).
     *
     * @param array $data
     * @return Formation
     */
    public static function create_from_array( array $data )
    {
        $formation = new self();

        // Title (= nom)
        if ( isset( $data['nom'] ) ) {
            $formation->set_nom( $data['nom'] );
        }

        // Contenu (= descriptif)
        if ( isset( $data['descriptif'] ) ) {
            $formation->set_descriptif( $data['descriptif'] );
        }

        if ( isset( $data['duree'] ) ) {
            $formation->set_duree( $data['duree'] );
        }

        if ( isset( $data['formateur'] ) ) {
            $formation->set_formateur( $data['formateur'] );
        }

        if ( isset( $data['public_cible'] ) ) {
            $formation->set_public_cible( $data['public_cible'] );
        }

        if ( isset( $data['pilier'] ) ) {
            $formation->set_pilier( $data['pilier'] );
        }

        if ( isset( $data['places_disponibles'] ) ) {
            $formation->set_places_disponibles( (int) $data['places_disponibles'] );
        }

        if ( isset( $data['min_inscriptions'] ) ) {
            $formation->set_min_inscriptions( (int) $data['min_inscriptions'] );
        }

        return $formation;
    }

    /* ==================== CRUD INSTANCE ==================== */

    /**
     * Sauvegarde la formation (insert ou update).
     */
    public function save()
    {
        $post_data = [
            'post_type'   => self::POST_TYPE,
            'post_title'  => $this->get_nom(),
            'post_content'=> $this->get_descriptif(),
            'post_status' => 'publish',
        ];

        if ( $this->post && $this->post->ID ) {
            $post_data['ID'] = $this->post->ID;
            $post_id = wp_update_post( $post_data, true );
        } else {
            $post_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $this->post = get_post( $post_id );

        // Sauvegarde des metas
        update_post_meta( $post_id, '_formation_duree',            $this->duree );
        update_post_meta( $post_id, '_formation_formateur',        $this->formateur );
        update_post_meta( $post_id, '_formation_public_cible',     $this->public_cible );
        update_post_meta( $post_id, '_formation_pilier',           $this->pilier );
        update_post_meta( $post_id, '_formation_places',           (int) $this->places_disponibles );
        update_post_meta( $post_id, '_formation_min_inscr',        (int) $this->min_inscriptions );

        return $post_id;
    }

    /**
     * Supprime la formation.
     */
    public function delete( $force_delete = false )
    {
        if ( ! $this->post || ! $this->post->ID ) {
            return false;
        }

        $result = wp_delete_post( $this->post->ID, $force_delete );
        if ( $result ) {
            $this->post = null;
        }

        return $result;
    }

    /**
     * Charge les metas dans les propriétés.
     */
    protected function load_meta()
    {
        if ( ! $this->post ) {
            return;
        }

        $post_id = $this->post->ID;

        $this->duree             = get_post_meta( $post_id, '_formation_duree', true );
        $this->formateur         = get_post_meta( $post_id, '_formation_formateur', true );
        $this->public_cible      = get_post_meta( $post_id, '_formation_public_cible', true );
        $this->pilier            = get_post_meta( $post_id, '_formation_pilier', true );
        $this->places_disponibles= (int) get_post_meta( $post_id, '_formation_places', true );
        $this->min_inscriptions  = (int) get_post_meta( $post_id, '_formation_min_inscr', true );
    }

    /* ==================== GETTERS / SETTERS ==================== */

    public function get_id()
    {
        return $this->post ? $this->post->ID : null;
    }

    public function get_nom()
    {
        return $this->post ? $this->post->post_title : '';
    }

    public function set_nom( $nom )
    {
        if ( ! $this->post ) {
            $this->post = new stdClass();
        }
        $this->post->post_title = $nom;
    }

    public function get_descriptif()
    {
        return $this->post ? $this->post->post_content : '';
    }

    public function set_descriptif( $descriptif )
    {
        if ( ! $this->post ) {
            $this->post = new stdClass();
        }
        $this->post->post_content = $descriptif;
    }

    public function get_duree()
    {
        return $this->duree;
    }

    public function set_duree( $duree )
    {
        $this->duree = $duree;
    }

    public function get_formateur()
    {
        return $this->formateur;
    }

    public function set_formateur( $formateur )
    {
        $this->formateur = $formateur;
    }

    public function get_public_cible()
    {
        return $this->public_cible;
    }

    public function set_public_cible( $public_cible )
    {
        $this->public_cible = $public_cible;
    }

    public function get_pilier()
    {
        return $this->pilier;
    }

    public function set_pilier( $pilier )
    {
        $this->pilier = $pilier;
    }

    public function get_places_disponibles()
    {
        return $this->places_disponibles;
    }

    public function set_places_disponibles( $places )
    {
        $this->places_disponibles = (int) $places;
    }

    public function get_min_inscriptions()
    {
        return $this->min_inscriptions;
    }

    public function set_min_inscriptions( $min )
    {
        $this->min_inscriptions = (int) $min;
    }

    /**
     * Accès brut au WP_Post si besoin.
     */
    public function get_post()
    {
        return $this->post;
    }
}
