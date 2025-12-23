<?php

/**
 * Tesseract OCR Processor
 * 
 * Wrapper class for Tesseract OCR to extract text from images.
 * Handles image preprocessing, OCR execution, and error handling.
 */

class OCRProcessor
{

    private $tesseract_path;
    private $temp_dir;
    private $supported_languages;
    private $last_error;

    /**
     * Constructor
     * 
     * @param string $tesseract_path Path to tesseract executable (auto-detected if null)
     */
    public function __construct($tesseract_path = null)
    {
        // Auto-detect Tesseract path based on OS
        if ($tesseract_path === null) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows paths to check
                $possible_paths = [
                    'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
                    'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
                    'tesseract' // If in PATH
                ];
            } else {
                // Linux/Mac paths
                $possible_paths = [
                    '/usr/bin/tesseract',
                    '/usr/local/bin/tesseract',
                    'tesseract' // If in PATH
                ];
            }

            foreach ($possible_paths as $path) {
                if ($this->testTesseractPath($path)) {
                    $this->tesseract_path = $path;
                    break;
                }
            }
        } else {
            $this->tesseract_path = $tesseract_path;
        }

        $this->temp_dir = __DIR__ . '/../uploads/ocr_temp';
        $this->supported_languages = ['eng']; // Default to English
        $this->last_error = null;

        // Ensure temp directory exists
        if (!file_exists($this->temp_dir)) {
            mkdir($this->temp_dir, 0755, true);
        }
    }

    /**
     * Test if Tesseract is available at the given path
     * 
     * @param string $path Path to test
     * @return bool True if Tesseract is available
     */
    private function testTesseractPath($path)
    {
        $escaped_path = escapeshellarg($path);
        $output = [];
        $return_code = -1;

        // Use different command for Windows vs Unix
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @exec("$escaped_path --version 2>&1", $output, $return_code);
        } else {
            @exec("$escaped_path --version 2>&1", $output, $return_code);
        }

        return $return_code === 0;
    }

    /**
     * Check if Tesseract is properly configured
     * 
     * @return array Status information
     */
    public function checkStatus()
    {
        $status = [
            'installed' => false,
            'path' => $this->tesseract_path,
            'version' => null,
            'languages' => [],
            'error' => null
        ];

        if (empty($this->tesseract_path)) {
            $status['error'] = 'Tesseract not found. Please install Tesseract OCR.';
            return $status;
        }

        // Check version
        $escaped_path = escapeshellarg($this->tesseract_path);
        $output = [];
        $return_code = -1;

        exec("$escaped_path --version 2>&1", $output, $return_code);

        if ($return_code === 0 && !empty($output)) {
            $status['installed'] = true;
            $status['version'] = $output[0] ?? 'Unknown';

            // Get available languages
            $lang_output = [];
            exec("$escaped_path --list-langs 2>&1", $lang_output, $lang_code);
            if ($lang_code === 0) {
                // Skip first line which is the header
                array_shift($lang_output);
                $status['languages'] = $lang_output;
            }
        } else {
            $status['error'] = 'Tesseract found but not responding correctly.';
        }

        return $status;
    }

    /**
     * Check if Tesseract OCR is available
     * 
     * @return bool True if Tesseract is installed and working
     */
    public function isAvailable()
    {
        $status = $this->checkStatus();
        return $status['installed'];
    }

    /**
     * Get the Tesseract executable path
     * 
     * @return string|null Path to tesseract or null if not found
     */
    public function getTesseractPath()
    {
        return $this->tesseract_path;
    }

    /**
     * Extract text from an image file
     * 
     * @param string $image_path Path to the image file
     * @param array $options OCR options
     * @return array Result with 'success', 'text', 'confidence', 'error'
     */
    public function extractText($image_path, $options = [])
    {
        $result = [
            'success' => false,
            'text' => '',
            'confidence' => 0,
            'error' => null,
            'processing_time' => 0
        ];

        $start_time = microtime(true);

        // Validate input
        if (!file_exists($image_path)) {
            $result['error'] = 'Image file not found: ' . $image_path;
            $this->last_error = $result['error'];
            return $result;
        }

        if (empty($this->tesseract_path)) {
            $result['error'] = 'Tesseract not configured. Please install Tesseract OCR.';
            $this->last_error = $result['error'];
            return $result;
        }

        // Prepare options
        $language = $options['language'] ?? 'eng';
        $psm = $options['psm'] ?? 3; // Page segmentation mode (3 = fully automatic)
        $oem = $options['oem'] ?? 3; // OCR Engine mode (3 = default)

        // Generate unique output filename
        $output_base = $this->temp_dir . '/' . uniqid('ocr_', true);
        $output_file = $output_base . '.txt';

        // Preprocess image if needed
        $processed_image = $this->preprocessImage($image_path, $options);
        $image_to_process = $processed_image ?: $image_path;

        // Build Tesseract command
        $escaped_tesseract = escapeshellarg($this->tesseract_path);
        $escaped_image = escapeshellarg($image_to_process);
        $escaped_output = escapeshellarg($output_base);

        $cmd = "$escaped_tesseract $escaped_image $escaped_output";
        $cmd .= " -l " . escapeshellarg($language);
        $cmd .= " --psm $psm";
        $cmd .= " --oem $oem";

        // Add config for better output
        $cmd .= " 2>&1";

        // Execute OCR
        $output = [];
        $return_code = -1;
        exec($cmd, $output, $return_code);

        // Clean up preprocessed image if we created one
        if ($processed_image && $processed_image !== $image_path && file_exists($processed_image)) {
            @unlink($processed_image);
        }

        // Check result
        if ($return_code === 0 && file_exists($output_file)) {
            $text = file_get_contents($output_file);
            $text = $this->cleanOCRText($text);

            $result['success'] = true;
            $result['text'] = $text;
            $result['confidence'] = $this->estimateConfidence($text);

            // Clean up output file
            @unlink($output_file);
        } else {
            $error_message = implode("\n", $output);
            $result['error'] = "OCR failed: " . ($error_message ?: "Unknown error (code: $return_code)");
            $this->last_error = $result['error'];
        }

        $result['processing_time'] = round(microtime(true) - $start_time, 3);

        return $result;
    }

    /**
     * Preprocess image to improve OCR accuracy
     * 
     * @param string $image_path Original image path
     * @param array $options Processing options
     * @return string|null Path to processed image or null if no processing needed/possible
     */
    private function preprocessImage($image_path, $options = [])
    {
        // Skip preprocessing if disabled
        if (isset($options['preprocess']) && $options['preprocess'] === false) {
            return null;
        }

        // Check if GD is available
        if (!extension_loaded('gd')) {
            return null;
        }

        try {
            // Get image info
            $image_info = @getimagesize($image_path);
            if ($image_info === false) {
                return null;
            }

            $mime_type = $image_info['mime'];

            // Load image based on type
            switch ($mime_type) {
                case 'image/jpeg':
                    $image = @imagecreatefromjpeg($image_path);
                    break;
                case 'image/png':
                    $image = @imagecreatefrompng($image_path);
                    break;
                case 'image/gif':
                    $image = @imagecreatefromgif($image_path);
                    break;
                case 'image/bmp':
                case 'image/x-ms-bmp':
                    $image = @imagecreatefrombmp($image_path);
                    break;
                case 'image/webp':
                    $image = @imagecreatefromwebp($image_path);
                    break;
                default:
                    return null;
            }

            if (!$image) {
                return null;
            }

            $width = imagesx($image);
            $height = imagesy($image);

            // Convert to grayscale for better OCR
            if (!isset($options['keep_color']) || $options['keep_color'] === false) {
                imagefilter($image, IMG_FILTER_GRAYSCALE);
            }

            // Increase contrast
            if (!isset($options['skip_contrast']) || $options['skip_contrast'] === false) {
                imagefilter($image, IMG_FILTER_CONTRAST, -20);
            }

            // Sharpen the image slightly
            if (!isset($options['skip_sharpen']) || $options['skip_sharpen'] === false) {
                $sharpen_matrix = [
                    [0, -1, 0],
                    [-1, 5, -1],
                    [0, -1, 0]
                ];
                imageconvolution($image, $sharpen_matrix, 1, 0);
            }

            // Save processed image
            $processed_path = $this->temp_dir . '/' . uniqid('processed_', true) . '.png';
            imagepng($image, $processed_path, 9);
            imagedestroy($image);

            return $processed_path;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Clean up OCR output text
     * 
     * @param string $text Raw OCR text
     * @return string Cleaned text
     */
    private function cleanOCRText($text)
    {
        // Remove excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove excessive newlines (more than 2 consecutive)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Trim each line
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $text = implode("\n", $lines);

        // Final trim
        $text = trim($text);

        return $text;
    }

    /**
     * Estimate confidence based on text quality
     * This is a heuristic since Tesseract's confidence is per-word
     * 
     * @param string $text OCR text
     * @return float Confidence score 0-100
     */
    private function estimateConfidence($text)
    {
        if (empty($text)) {
            return 0;
        }

        $confidence = 100;

        // Reduce confidence for very short text
        $length = strlen($text);
        if ($length < 10) {
            $confidence -= 30;
        } elseif ($length < 50) {
            $confidence -= 15;
        }

        // Reduce confidence for high special character ratio
        $special_chars = preg_match_all('/[^a-zA-Z0-9\s.,!?;:\'"()-]/', $text);
        $special_ratio = $special_chars / max(1, $length);
        if ($special_ratio > 0.2) {
            $confidence -= 25;
        } elseif ($special_ratio > 0.1) {
            $confidence -= 10;
        }

        // Reduce confidence for too many consecutive special characters
        if (preg_match('/[^a-zA-Z0-9\s]{5,}/', $text)) {
            $confidence -= 20;
        }

        // Reduce confidence if very few recognizable words
        $words = preg_match_all('/\b[a-zA-Z]{3,}\b/', $text);
        if ($words < 3) {
            $confidence -= 20;
        }

        return max(0, min(100, $confidence));
    }

    /**
     * Extract text from multiple images and combine
     * 
     * @param array $image_paths Array of image paths
     * @param array $options OCR options
     * @return array Combined result
     */
    public function extractTextFromMultiple($image_paths, $options = [])
    {
        $combined_result = [
            'success' => true,
            'text' => '',
            'confidence' => 0,
            'pages' => [],
            'total_processing_time' => 0,
            'errors' => []
        ];

        $total_confidence = 0;
        $successful_pages = 0;

        foreach ($image_paths as $index => $image_path) {
            $page_num = $index + 1;
            $result = $this->extractText($image_path, $options);

            $combined_result['pages'][$page_num] = $result;
            $combined_result['total_processing_time'] += $result['processing_time'];

            if ($result['success']) {
                $combined_result['text'] .= "\n--- Page $page_num ---\n" . $result['text'] . "\n";
                $total_confidence += $result['confidence'];
                $successful_pages++;
            } else {
                $combined_result['errors'][] = "Page $page_num: " . $result['error'];
            }
        }

        // Calculate average confidence
        if ($successful_pages > 0) {
            $combined_result['confidence'] = round($total_confidence / $successful_pages, 2);
        }

        // Mark as failed if no pages succeeded
        if ($successful_pages === 0) {
            $combined_result['success'] = false;
        }

        $combined_result['text'] = trim($combined_result['text']);

        return $combined_result;
    }

    /**
     * Extract text from a PDF file
     * Converts PDF pages to images first, then performs OCR
     * 
     * @param string $pdf_path Path to PDF file
     * @param array $options OCR options
     * @return array Result with extracted text
     */
    public function extractTextFromPDF($pdf_path, $options = [])
    {
        $result = [
            'success' => false,
            'text' => '',
            'confidence' => 0,
            'error' => null,
            'pages' => [],
            'processing_time' => 0
        ];

        if (!file_exists($pdf_path)) {
            $result['error'] = 'PDF file not found: ' . $pdf_path;
            return $result;
        }

        // Check if ImageMagick is available for PDF conversion
        $convert_path = $this->findImageMagick();

        if (!$convert_path) {
            // Try using Ghostscript directly
            $gs_path = $this->findGhostscript();

            if (!$gs_path) {
                $result['error'] = 'ImageMagick or Ghostscript required for PDF processing. Please install one of them.';
                return $result;
            }

            return $this->extractTextFromPDFWithGhostscript($pdf_path, $gs_path, $options);
        }

        return $this->extractTextFromPDFWithImageMagick($pdf_path, $convert_path, $options);
    }

    /**
     * Find ImageMagick convert command
     * 
     * @return string|null Path to convert or null if not found
     */
    private function findImageMagick()
    {
        $paths = [];

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $paths = [
                'C:\\Program Files\\ImageMagick-7.1.1-Q16-HDRI\\magick.exe',
                'C:\\Program Files\\ImageMagick-7.0.0-Q16\\convert.exe',
                'magick',
                'convert'
            ];
        } else {
            $paths = ['/usr/bin/convert', '/usr/local/bin/convert', 'convert'];
        }

        foreach ($paths as $path) {
            $output = [];
            $return_code = -1;
            @exec(escapeshellarg($path) . " --version 2>&1", $output, $return_code);
            if ($return_code === 0 && stripos(implode('', $output), 'ImageMagick') !== false) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Find Ghostscript
     * 
     * @return string|null Path to gs or null if not found
     */
    private function findGhostscript()
    {
        $paths = [];

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $paths = [
                'C:\\Program Files\\gs\\gs10.02.1\\bin\\gswin64c.exe',
                'C:\\Program Files\\gs\\gs9.56.1\\bin\\gswin64c.exe',
                'gswin64c',
                'gswin32c'
            ];
        } else {
            $paths = ['/usr/bin/gs', '/usr/local/bin/gs', 'gs'];
        }

        foreach ($paths as $path) {
            $output = [];
            $return_code = -1;
            @exec(escapeshellarg($path) . " --version 2>&1", $output, $return_code);
            if ($return_code === 0) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Extract text from PDF using ImageMagick
     */
    private function extractTextFromPDFWithImageMagick($pdf_path, $convert_path, $options)
    {
        $result = [
            'success' => false,
            'text' => '',
            'confidence' => 0,
            'error' => null,
            'pages' => [],
            'processing_time' => 0
        ];

        $start_time = microtime(true);
        $temp_prefix = $this->temp_dir . '/' . uniqid('pdf_page_', true);

        // Convert PDF to images
        $escaped_convert = escapeshellarg($convert_path);
        $escaped_pdf = escapeshellarg($pdf_path);
        $escaped_output = escapeshellarg($temp_prefix . '-%03d.png');

        $dpi = $options['dpi'] ?? 300;
        $cmd = "$escaped_convert -density $dpi $escaped_pdf $escaped_output 2>&1";

        $output = [];
        $return_code = -1;
        exec($cmd, $output, $return_code);

        if ($return_code !== 0) {
            $result['error'] = 'Failed to convert PDF to images: ' . implode("\n", $output);
            return $result;
        }

        // Find generated images
        $image_files = glob($temp_prefix . '-*.png');
        sort($image_files);

        if (empty($image_files)) {
            // Try single page format
            $single_image = $temp_prefix . '.png';
            if (file_exists($single_image)) {
                $image_files = [$single_image];
            } else {
                $result['error'] = 'No images generated from PDF';
                return $result;
            }
        }

        // OCR each page
        $ocr_result = $this->extractTextFromMultiple($image_files, $options);

        // Clean up temp images
        foreach ($image_files as $img) {
            @unlink($img);
        }

        $ocr_result['processing_time'] = round(microtime(true) - $start_time, 3);

        return $ocr_result;
    }

    /**
     * Extract text from PDF using Ghostscript
     */
    private function extractTextFromPDFWithGhostscript($pdf_path, $gs_path, $options)
    {
        $result = [
            'success' => false,
            'text' => '',
            'confidence' => 0,
            'error' => null,
            'pages' => [],
            'processing_time' => 0
        ];

        $start_time = microtime(true);
        $temp_prefix = $this->temp_dir . '/' . uniqid('pdf_page_', true);

        // Get page count first (simplified - just try to convert)
        $escaped_gs = escapeshellarg($gs_path);
        $escaped_pdf = escapeshellarg($pdf_path);
        $output_pattern = $temp_prefix . '-%03d.png';

        $dpi = $options['dpi'] ?? 300;
        $cmd = "$escaped_gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r$dpi -sOutputFile=" .
            escapeshellarg($output_pattern) . " $escaped_pdf 2>&1";

        $output = [];
        $return_code = -1;
        exec($cmd, $output, $return_code);

        // Find generated images
        $image_files = glob($temp_prefix . '-*.png');
        sort($image_files);

        if (empty($image_files)) {
            $result['error'] = 'Failed to convert PDF: ' . implode("\n", $output);
            return $result;
        }

        // OCR each page
        $ocr_result = $this->extractTextFromMultiple($image_files, $options);

        // Clean up temp images
        foreach ($image_files as $img) {
            @unlink($img);
        }

        $ocr_result['processing_time'] = round(microtime(true) - $start_time, 3);

        return $ocr_result;
    }

    /**
     * Get the last error message
     * 
     * @return string|null Last error message
     */
    public function getLastError()
    {
        return $this->last_error;
    }

    /**
     * Set the Tesseract path manually
     * 
     * @param string $path Path to tesseract executable
     */
    public function setTesseractPath($path)
    {
        $this->tesseract_path = $path;
    }

    /**
     * Clean up old temp files (call periodically)
     * 
     * @param int $max_age_seconds Maximum age of files to keep (default: 1 hour)
     * @return int Number of files cleaned up
     */
    public function cleanupTempFiles($max_age_seconds = 3600)
    {
        $count = 0;
        $files = glob($this->temp_dir . '/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $max_age_seconds) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}

// ============================================
// Helper Functions (procedural wrappers)
// ============================================

/**
 * Quick OCR function for single image
 * 
 * @param string $image_path Path to image
 * @param array $options OCR options
 * @return array Result
 */
function ocr_extract_text($image_path, $options = [])
{
    $ocr = new OCRProcessor();
    return $ocr->extractText($image_path, $options);
}

/**
 * Check if OCR is available on this system
 * 
 * @return array Status information
 */
function ocr_check_status()
{
    $ocr = new OCRProcessor();
    return $ocr->checkStatus();
}
