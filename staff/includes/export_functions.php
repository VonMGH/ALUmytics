<?php
/**
 * Export Functions for ALUMytics System
 * Handles PDF and CSV export functionality
 */

class ExportFunctions {
    
    /**
     * Export data to PDF format
     * @param array $data - Array of data to export
     * @param string $title - Title for the PDF document
     * @param string $filename - Optional filename (auto-generated if not provided)
     */
    public static function exportToPDF($data, $title = 'ALUMytics Export', $filename = null) {
        if ($filename === null) {
            $filename = 'export_' . date('Y-m-d_H-i-s') . '.pdf';
        }
        
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Simple PDF generation (basic implementation)
        // Note: For production, consider using libraries like TCPDF, FPDF, or mPDF
        $content = self::generateSimplePDF($data, $title);
        echo $content;
        exit;
    }
    
    /**
     * Export system data to CSV format
     * @param mysqli $conn - Database connection
     * @param string $format - Export format (csv)
     * @param array $tables - Tables to export
     */
    public static function exportSystemData($conn, $format = 'csv', $tables = ['users', 'colleges'], $metrics = null) {
        $filename = 'system_data_' . date('Y-m-d_H-i-s') . '.' . $format;
        
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            $output = fopen('php://output', 'w');

            // Optional: prepend system metrics section
            if (is_array($metrics) && !empty($metrics)) {
                fputcsv($output, ["=== SYSTEM METRICS ==="]);
                fputcsv($output, ['Metric', 'Value']);
                foreach ($metrics as $metricName => $metricValue) {
                    fputcsv($output, [$metricName, $metricValue]);
                }
                // Separator
                fputcsv($output, []);
            }
            
            // Export each table
            foreach ($tables as $table) {
                self::exportTableToCSV($conn, $table, $output);
            }
            
            fclose($output);
            exit;
        }
    }
    
    /**
     * Export a specific table to CSV
     * @param mysqli $conn - Database connection
     * @param string $table - Table name
     * @param resource $output - Output stream
     */
    private static function exportTableToCSV($conn, $table, $output) {
        // Add table header
        fputcsv($output, ["=== $table DATA ==="]);
        
        // Get table structure
        $result = $conn->query("DESCRIBE `$table`");
        if (!$result) {
            return;
        }
        
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        // Write column headers
        fputcsv($output, $columns);
        
        // Get and write data
        $dataResult = $conn->query("SELECT * FROM `$table`");
        if ($dataResult && $dataResult->num_rows > 0) {
            while ($row = $dataResult->fetch_assoc()) {
                fputcsv($output, array_values($row));
            }
        }
        
        // Add empty row for separation
        fputcsv($output, []);
    }
    
    /**
     * Generate a simple PDF content
     * @param array $data - Data to include in PDF
     * @param string $title - PDF title
     * @return string - PDF content
     */
    private static function generateSimplePDF($data, $title) {
        // This is a very basic PDF implementation
        // For production use, implement proper PDF library integration
        
        $content = "%PDF-1.4\n";
        $content .= "1 0 obj\n";
        $content .= "<<\n";
        $content .= "/Type /Catalog\n";
        $content .= "/Pages 2 0 R\n";
        $content .= ">>\n";
        $content .= "endobj\n\n";
        
        $content .= "2 0 obj\n";
        $content .= "<<\n";
        $content .= "/Type /Pages\n";
        $content .= "/Kids [3 0 R]\n";
        $content .= "/Count 1\n";
        $content .= ">>\n";
        $content .= "endobj\n\n";
        
        $pageContent = "BT\n";
        $pageContent .= "/F1 12 Tf\n";
        $pageContent .= "100 750 Td\n";
        $pageContent .= "($title) Tj\n";
        $pageContent .= "0 -20 Td\n";
        
        // Add data to PDF
        if (is_array($data)) {
            foreach ($data as $row) {
                if (is_array($row)) {
                    $line = implode(' | ', array_map(function($item) {
                        return str_replace(['(', ')'], ['[', ']'], (string)$item);
                    }, $row));
                } else {
                    $line = str_replace(['(', ')'], ['[', ']'], (string)$row);
                }
                $pageContent .= "($line) Tj\n";
                $pageContent .= "0 -15 Td\n";
            }
        }
        
        $pageContent .= "ET\n";
        
        $content .= "3 0 obj\n";
        $content .= "<<\n";
        $content .= "/Type /Page\n";
        $content .= "/Parent 2 0 R\n";
        $content .= "/Resources <<\n";
        $content .= "/Font <<\n";
        $content .= "/F1 <<\n";
        $content .= "/Type /Font\n";
        $content .= "/Subtype /Type1\n";
        $content .= "/BaseFont /Helvetica\n";
        $content .= ">>\n";
        $content .= ">>\n";
        $content .= ">>\n";
        $content .= "/MediaBox [0 0 612 792]\n";
        $content .= "/Contents 4 0 R\n";
        $content .= ">>\n";
        $content .= "endobj\n\n";
        
        $content .= "4 0 obj\n";
        $content .= "<<\n";
        $content .= "/Length " . strlen($pageContent) . "\n";
        $content .= ">>\n";
        $content .= "stream\n";
        $content .= $pageContent;
        $content .= "endstream\n";
        $content .= "endobj\n\n";
        
        $content .= "xref\n";
        $content .= "0 5\n";
        $content .= "0000000000 65535 f \n";
        $content .= "0000000009 00000 n \n";
        $content .= "0000000074 00000 n \n";
        $content .= "0000000131 00000 n \n";
        $content .= "0000000422 00000 n \n";
        $content .= "trailer\n";
        $content .= "<<\n";
        $content .= "/Size 5\n";
        $content .= "/Root 1 0 R\n";
        $content .= ">>\n";
        $content .= "startxref\n";
        $content .= "542\n";
        $content .= "%%EOF\n";
        
        return $content;
    }
    
    /**
     * Export users data specifically
     * @param mysqli $conn - Database connection
     * @param string $format - Export format
     */
    public static function exportUsers($conn, $format = 'csv') {
        $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.' . $format;
        
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // Headers
            fputcsv($output, ['ID', 'Full Name', 'Email', 'Role', 'College', 'Registration Date', 'Last Login']);
            
            // Data
            $query = "SELECT u.id, u.full_name, u.email, u.role, c.college_name, u.created_at, u.last_login 
                     FROM users u 
                     LEFT JOIN colleges c ON u.college_id = c.id 
                     ORDER BY u.created_at DESC";
            
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    fputcsv($output, [
                        $row['id'],
                        $row['full_name'],
                        $row['email'],
                        $row['role'],
                        $row['college_name'] ?: 'N/A',
                        $row['created_at'],
                        $row['last_login'] ?: 'Never'
                    ]);
                }
            }
            
            fclose($output);
            exit;
        }
    }
    
    /**
     * Export colleges data specifically
     * @param mysqli $conn - Database connection
     * @param string $format - Export format
     */
    public static function exportColleges($conn, $format = 'csv') {
        $filename = 'colleges_export_' . date('Y-m-d_H-i-s') . '.' . $format;
        
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // Headers
            fputcsv($output, ['ID', 'College Name', 'Description', 'Total Users', 'Created Date']);
            
            // Data
            $query = "SELECT c.id, c.college_name, c.description, COUNT(u.id) as user_count, c.created_at 
                     FROM colleges c 
                     LEFT JOIN users u ON c.id = u.college_id 
                     GROUP BY c.id, c.college_name, c.description, c.created_at 
                     ORDER BY c.college_name";
            
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    fputcsv($output, [
                        $row['id'],
                        $row['college_name'],
                        $row['description'] ?: 'N/A',
                        $row['user_count'],
                        $row['created_at']
                    ]);
                }
            }
            
            fclose($output);
            exit;
        }
    }
}
?>
