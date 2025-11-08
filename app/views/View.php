<?php
/**
 * View Class - Simple view renderer
 */
class View {
    private $template;
    private $data;
    
    public function __construct($template, $data = []) {
        $this->template = $template;
        $this->data = $data;
    }
    
    /**
     * Render the view template
     */
    public function render() {
        // Extract data variables
        extract($this->data);
        
        // Include the view file
        $view_file = __DIR__ . '/' . $this->template . '.php';
        
        if (!file_exists($view_file)) {
            throw new Exception("View file not found: {$view_file}");
        }
        
        include $view_file;
    }
    
    /**
     * Escape HTML
     */
    public static function escape($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

