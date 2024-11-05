<?php

/**
 * FileManager class for handling file and directory operations.
 *
 * @property string $directoryInput The input directory or file path.
 * @property string $directoryOutput The output directory path (for copy/move actions).
 * @property string $action The action to perform (copy, move, delete).
 * @property bool   $isDir A boolean indicating whether the provided path is a directory (true) or a file (false).
 * @property string $root The root directory for file operations.
 * @property string $forceMode Flag to force actions (both, replace).
 * @property string $mergeMode Flag to determine merge mode for directory actions (both, merge).
 * @property array  $storage Storage for file and directory information.
 */
class FileManager {

    private $directoryInput;
    private $directoryOutput;
    private $action;
    private $isDir;
    private $root;
    private $forceMode;
    private $mergeMode;
    private $storage = [];

    /**
     * Constructor for the FileManager class.
     *
     * @param string|null $root The root directory for file operations. If not provided, the system's temporary directory will be used for enhanced security.
     */
    public function __construct(?string $root = null) {
        $this->root = $root ?? sys_get_temp_dir();
    }

    /**
     * Retrieves information about a specified directory or the root directory if no input is provided.
     *
     * @param string|null $in Optional. The path to the directory. If not provided, information about the root directory will be retrieved.
     * @return array An array containing information about the specified or root directory.
     */
    public function getDirectoryInfo(?string $in = null): array {
        $this->directoryInput = $in ? $this->resolveAbsolutePath($in) : $this->root;
        $this->isDir          = is_dir($this->directoryInput);

        $this->buildStorageStructure($this->directoryInput, $this->isDir, true);
        
        if ($this->directoryInput === $this->root) {
            $availableSpace = disk_free_space($this->root);

            $this->storage['name'] = DIRECTORY_SEPARATOR;
            $this->storage['path'] = DIRECTORY_SEPARATOR;
            $size = $this->storage['information']['size'];

            $percentFreeSpace = round((($availableSpace / ($size['bytes'] + $availableSpace)) * 100), 2);
    
            $size['percentage'] = 100 - $percentFreeSpace;
    
            $this->storage['information']['size'] = [
                'used' => $size,
                'available' => [
                    'size' => $availableSpace,
                    'formatted' => $this->formatBytes($availableSpace),
                    'percentage' => $percentFreeSpace
                ]
            ];
        };

        return $this->storage;
    }

    /**
     * Uploads a file to the specified directory.
     *
     * @param string $in The target directory path.
     * @param array $file The uploaded file data.
     * @throws Exception If the upload fails or encounters an error.
     */
    public function upload(string $in, array $file): void {
        $directory = $this->resolveAbsolutePath($in);

        if (!@is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Not a valid entry.');
        };

        if($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = array(
                UPLOAD_ERR_INI_SIZE   => "The file exceeds the maximum size defined by the server.",
                UPLOAD_ERR_FORM_SIZE  => "The file exceeds the maximum size defined in the HTML form.",
                UPLOAD_ERR_PARTIAL    => "The file was only partially uploaded.",
                UPLOAD_ERR_NO_FILE    => "No file was uploaded.",
                UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary directory.",
                UPLOAD_ERR_CANT_WRITE => "Failed to write the file to disk.",
                UPLOAD_ERR_EXTENSION  => "A PHP extension interrupted the upload."
            );

            $errorCode = $file['error'];
            $errorMessage = 
                $errorMessages[$errorCode] ?? 
                "Unknown error during upload. Code: $errorCode.";

            throw new Exception($errorMessage);
        };

        if ($file['size'] > disk_free_space($this->root)) {
            throw new Exception('Not enough disk space to upload this file.');
        };

        $fileUploadName = $file['name'];
        $fileTemporary  = $file['tmp_name'];

        list($fileDiretory, $fileName, $index) = $this->uniqueDirectory($directory, $fileUploadName);

        if (!@move_uploaded_file($fileTemporary, $fileDiretory)) {
            throw new Exception('Failed to move to the directory.');
        };
    }

    /**
     * Searches for files or directories based on a query string.
     *
     * @param string $in The input directory or file path.
     * @param string $query The search query.
     * @param string $sortType The type of sorting (name, type, size, timeCreated, timeModified).
     * @param string $order The sorting order (asc, desc).
     * @return array Result of the search operation.
     */
    public function search(string $in = '', string $query = '', string $sortType = 'name', string $order = 'asc'): array {
        if(empty($query)){
            throw new Exception("The search term is empty. Please provide a valid search term.");
        };

        $this->directoryInput = $this->resolveAbsolutePath($in);

        if(is_file($this->directoryInput) || is_link($this->directoryInput)){
            throw new Exception("The input directory is not a folder.");
        };

        foreach ($this->recursiveSearch(false) as $directory) {
            $info = pathinfo($directory->getRealPath());

            if(stripos($info['filename'], $query) !== false || stripos($info['basename'], $query) !== false) {
                $this->buildStorageStructure($directory->getRealPath(), $directory->isDir());
            };
        };

        $this->sortResult($sortType, $order);
        return $this->storage;
    }

    /**
     * Explores the contents of a directory.
     *
     * @param string $in The input directory path.
     * @param string $sortType The type of sorting (name, type, size, timeCreated, timeModified).
     * @param string $order The sorting order (asc, desc).
     * @return array Result of the exploration operation.
     * @throws Exception If the input directory is invalid or not readable.
     */
    public function explorer(string $in = '', string $sortType = 'name', string $order = 'asc'): array {
        $this->directoryInput = $this->resolveAbsolutePath($in);

        if(is_file($this->directoryInput) || is_link($this->directoryInput)){
            throw new Exception("The input directory is not a folder.");
        };

        foreach ($this->recursiveSearch(false, false) as $directory) {
            $this->buildStorageStructure($directory->getRealPath(), $directory->isDir());
        };

        $this->sortResult($sortType, $order);
        $this->storage['path'] = $this->directoryInput;

        return $this->storage;
    }

    /**
     * Executes file or directory operations (copy, move, delete).
     *
     * @param string          $action    The action to perform (copy, move, delete).
     * @param string          $in        The input directory or file path.
     * @param string|null     $out       The output directory path (for copy/move actions).
     * @param string|null     $forceMode Flag to forceMode actions (both, replace).
     * @param string|null     $mergeMode Flag to determine merge mode for directory actions (both, merge).
     * @return array Result of the execution operation.
     * @throws Exception If the action, forceMode or mergeMode is not recognized, or if there are errors during execution.
     */
    public function execute(?string $action = null, ?string $in = null, ?string $out = null, ?string $forceMode = null, ?string $mergeMode = null): array {
        $actionsAllowed   = ['delete', 'move', 'copy'];
        $forceModeAllowed = ['both', 'replace'];
        $mergeModeAllowed = ['both', 'merge'];

        if (!in_array($action, $actionsAllowed)) {
            throw new Exception('Action not recognized, use: "' . implode('", "', $actionsAllowed) . '".');
        };

        if ($forceMode !== null && !in_array($forceMode, $forceModeAllowed)) {
            throw new Exception('forceMode mode not recognized, use: "' . implode('", "', $forceModeAllowed) . '".');
        };

        $this->action          = $action;
        $this->forceMode       = $forceMode;
        $this->directoryInput  = $this->resolveAbsolutePath($in);
        $this->isDir           = is_dir($this->directoryInput);

        if (!is_readable($this->directoryInput)) {
            throw new Exception('The previous directory cannot be read.');
        };

        if (in_array($this->action, array_slice($actionsAllowed, 0, 2))) {
            if ($this->directoryInput === $this->root) {
                throw new Exception('You cannot perform these actions in the root directory.');
            };

            if (!is_writable(dirname($this->directoryInput))) {
                throw new Exception('The previous directory cannot be written.');
            };           
        };

        if ($this->action === 'delete') {
            $this->delete();
        } else {
            $directoryOutputReal = $this->resolveAbsolutePath(@dirname($out));

            if (!is_readable($directoryOutputReal)){
                throw new Exception('A destination directory cannot be read.');
            };

            if (!is_writable($directoryOutputReal)) {
                throw new Exception('The destination directory cannot be written.');
            };

            $this->directoryOutput = $directoryOutputReal . DIRECTORY_SEPARATOR . $this->sanitizePath(@basename($out));

            if ($this->action === 'move' && $this->directoryOutput === $this->directoryInput) {
                throw new Exception('The input directory is the same as the output directory.');
            };

            if ($this->action === 'copy' && $this->getSize($this->directoryInput)[0] > disk_free_space($this->root)) {
                throw new Exception('Not enough disk space to perform this action.');
            };

            if ($this->isDir) {
                if ($mergeMode !== null && !in_array($mergeMode, $mergeModeAllowed)) {
                    throw new Exception('mergeMode mode not recognized, use: "' . implode('", "', $mergeModeAllowed) . '".');
                };
    
                $this->mergeMode = $mergeMode;
                $this->transferFolder();
            } else {
                $this->transfer();
            };
        };

        return $this->storage;
    }

    /**
     * Resolves the absolute path for the provided relative path within the root directory.
     *
     * @param string $in The input directory or file path.
     * @return string The absolute path.
     * @throws Exception If the input directory does not exist or the path is not allowed.
     */
    public function resolveAbsolutePath(string $in = ''): string {
        $in = $this->sanitizePath($in);

        $in = realpath($this->root . DIRECTORY_SEPARATOR . $in);

        if ($in === false)                      throw new Exception('The input directory does not exist.');
        if (strpos($in, $this->root) === false) throw new Exception('Path not allowed.');

        return $in;
    }

    /**
     * Formats the given size in bytes into a human-readable string.
     *
     * @param int $bytes The size in bytes.
     * @param int $precision The number of decimal places.
     * @return string The formatted size string.
     */
    public function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . $units[$pow];
    }

    /**
     * Recursive function to clean empty subdirectories in a specified directory.
     * Useful for maintaining a neat directory structure in code projects.
     *
     * @param string $p The path to the directory to be cleaned.
     * @return bool Returns true if the cleaning operation is successful, false otherwise.
     */
    private function cleanSubfolders(string $p): bool {
        $g = $p . DIRECTORY_SEPARATOR . "*"; $e = true;
        foreach (glob($g) as $f) $e &= is_dir($f) && $this->cleanSubfolders($f);
        return $e && empty(glob($g)) && rmdir($p);
    }

    /**
     * Automatically renames a file or directory to avoid conflicts.
     *
     * @param string $in The target directory path.
     * @param string|null $name The original name of the file or directory.
     * @return array An array containing the new directory, name, and index.
     */
    private function uniqueDirectory(string $in, ?string $name = null): array {
        $directory = $name ? $in . DIRECTORY_SEPARATOR . $name : $in;
        $isDir = is_dir($directory);
        $index = 0;

        if($isDir || (file_exists($directory) || is_link($directory))) {

            $info = pathinfo($directory);

            if ($isDir) {
                $filename  = $info['basename'];
                $extension = '';
            } else {
                $filename  = $info['filename'];
                $extension = '.' . $info['extension'];
            };

            do {
                ++$index;

                $newName = "$filename-$index$extension";
                $testDirectory = dirname($directory) . DIRECTORY_SEPARATOR . $newName;

            } while (is_dir($testDirectory) || (file_exists($testDirectory) || is_link($testDirectory)));

            $name = $newName;
        };

        $directory = dirname($directory) . DIRECTORY_SEPARATOR . $name;

        return [$directory, $name, $index];
    }

    /**
     * Creates a structured representation of a file or directory and adds it to storage.
     *
     * @param string $directory An string representing a file or directory.
     * @param bool $isDir A boolean indicating whether the provided path is a directory (true) or a file (false).
     * @param bool|null $ssf Optional. If true, the storage will be replaced with the created structure; if false, the structure will be added to the appropriate section (files or folders).
     */
    private function buildStorageStructure(string $realDirectory, bool $isDir, ?bool $ssf = null): void {
        $fakeDirectory = trim(substr($realDirectory, strlen($this->root)), DIRECTORY_SEPARATOR);

        $created  = filectime($realDirectory);
        $modified = filemtime($realDirectory);

        list($size, $files, $folders) = $this->getSize($realDirectory, $isDir);

        $base = [
            'name' => basename($realDirectory),
            'path' => $fakeDirectory,
            'time' => [
                'created'  => [
                    'timestamp' => $created,
                    'formatted' => date('Y-m-d H:i:s', $created)
                ],
                'modified' => [
                    'timestamp' => $modified,
                    'formatted' => date('Y-m-d H:i:s', $modified)
                ]
            ],
            'information' => [
                'type' => @mime_content_type($realDirectory),
                'size' => [
                    'bytes'     => $size,
                    'formatted' => $this->formatBytes($size)
                ]
            ],
            'readable' => is_readable($realDirectory),
            'writable' => is_writable($realDirectory)
        ];

        if (PHP_OS !== 'WINNT'){
            $permissions = fileperms($realDirectory);
            $permissions = sprintf('%o', $permissions);
            $permissions = substr($permissions, -4);

            $base['information']['permissions'] = $permissions;
            $base['information']['owner'] = posix_getpwuid(fileowner($realDirectory))['name'];
            $base['information']['group'] = posix_getgrgid(filegroup($realDirectory))['name'];
        };

        if (is_int($files))   $base['information']['subfiles']   = $files;
        if (is_int($folders)) $base['information']['subfolders'] = $folders;

        if ($ssf) {
            $this->storage = $base;
        } else {
            $this->storage[$isDir ? 'folders' : 'files'][] = $base;
        };
    }

    /**
     * Sorts the results in storage based on the specified criteria.
     *
     * @param string $sortType The type of sorting (name, type, size, timeCreated, timeModified).
     * @param string $order The sorting order (asc, desc).
     * @throws Exception If the sort type or order is invalid.
     */
    private function sortResult(string $sortType, string $order): void {
        if (!in_array($order, ['asc', 'desc'])) {
            throw new Exception('Invalid order type. Available options: "asc", "desc".');
        };

        $sorting = [
            'name' => function ($a, $b) use ($order) {
                $result = strcmp($a["name"], $b["name"]);
                return ($order == 'desc') ? -$result : $result;
            },
            'type' => function ($a, $b) use ($order) {
                $result = strcmp($a['information']["type"], $b['information']["type"]);
                return ($order == 'desc') ? -$result : $result;
            },
            'size' => function ($a, $b) use ($order) {
                $result = $a["information"]['size']['bytes'] - $b["information"]['size']['bytes'];
                return ($order == 'desc') ? -$result : $result;
            },
            'timeCreated' => function ($a, $b) use ($order) {
                $result = $a["time"]['created'] - $b["time"]['created'];
                return ($order == 'desc') ? -$result : $result;
            },
            'timeModified' => function ($a, $b) use ($order) {
                $result = $a["time"]['modified'] - $b["time"]['modified'];
                return ($order == 'desc') ? -$result : $result;
            }
        ];

        $availableSorts = array_keys($sorting);

        if (!in_array($sortType, $availableSorts)) {
            throw new Exception('Invalid sort type. Available options: "' . implode('", "', $availableSorts) . '".');
        };

        if(!empty($this->storage['folders'])) usort($this->storage['folders'], $sorting[$sortType]);
        if(!empty($this->storage['files']))   usort($this->storage['files'],   $sorting[$sortType]);
    }

    /**
     * Retrieves information about the contents of a directory.
     *
     * @param string|null $directory The directory path (optional, defaults to the input directory).
     * @param bool|null   $isDir A boolean indicating whether the provided path is a directory (true) or a file (false).
     */
    private function getSize(?string $directory = null, ?bool $isDir = null): array {
        $directory = $directory ?? $this->directoryInput;
        $isDir     = $isDir     ?? is_dir($directory);

        if(!$isDir){
            return [filesize($directory), null, null];
        };

        $recursiveIterator = $this->recursiveSearch(false, true, $directory);

        $size = $files = $folders = 0;

        foreach ($recursiveIterator as $directory) {
            if($directory->isDir()) {
                ++$folders;
                continue;
            };

            $size += filesize($directory->getRealPath());
            ++$files;
        };

        return [$size, $files, $folders];
    }

    /**
     * Filters and sanitizes the input directory or file path.
     *
     * @param string $in The input directory or file path.
     * @return string The filtered and sanitized path.
     */
    private function sanitizePath(string $in = ''): string {
        return trim($in, DIRECTORY_SEPARATOR);
    }

    /**
     * Performs a recursive search on the directory and returns an iterator.
     *
     * @param bool $childFirst Whether to process child elements first in the iteration.
     * @param bool $subPath Whether to include sub-paths in the iterator.
     * @param string|null $directory The directory to search (optional, defaults to the input directory).
     * @return RecursiveIteratorIterator The recursive iterator.
     */
    private function recursiveSearch(bool $childFirst = false, bool $subPath = true, ?string $directory = null): \RecursiveIteratorIterator {
        $directory = $directory ?? $this->directoryInput;
        $skipDots  = \RecursiveDirectoryIterator::SKIP_DOTS;

        $pathOption =  $subPath ?
            $childFirst ?
                \RecursiveIteratorIterator::CHILD_FIRST :
                \RecursiveIteratorIterator::SELF_FIRST
            : $skipDots;

        $recursiveDirectory = new \RecursiveDirectoryIterator($directory, $skipDots);
        $recursiveIterator  = new \RecursiveIteratorIterator($recursiveDirectory, $pathOption);

        return $recursiveIterator;
    }

    /**
     * Transfers a file or directory to a specified destination.
     *
     * @param string|null $directory The source directory or file path (optional, defaults to the input directory).
     * @param string|null $destiny The destination directory or file path (optional, defaults to the output directory).
     */
    private function transfer(?string $directory = null, ?string $destiny = null) : void {
        $directory = $directory ?? $this->directoryInput;
        $destiny   = $destiny   ?? $this->directoryOutput;
        $result    = null;

        try {
            list($newDestiny, $newName, $index) = $this->uniqueDirectory($destiny);

            if($index > 0) {
                if ($this->forceMode === null) {
                    throw new Exception('There is already a directory with the same name at the destination.');
                } elseif ($this->forceMode === 'replace') {
                    if(!@unlink($destiny)) {
                        throw new Exception('Failed to delete the file in the destination directory.');
                    };
                } elseif ($this->forceMode === 'both') {
                    $destiny = $newDestiny;
                };
            };

            $permissions = PHP_OS !== 'WINNT' ? fileperms($directory) : null;

            if ($this->action === 'move') {
                $result = @rename($directory, $destiny);
            } else {
                $result = is_link($directory)                 ?
                    @symlink(@readlink($directory), $destiny) :
                    @copy($directory, $destiny)               ;
            };

            if (!$result) {
                throw new Exception("The $this->action operation failed.");
            };

            if ($permissions){
                if (!@chmod($destiny, $permissions)) {
                    throw new Exception('Failed to apply permissions.');
                };
            };

        } catch (Exception $e) {
            $this->storage[] = [
                'directory' => [
                    'in'  => $directory,
                    'out' => $destiny
                ],
                'transfer' => $result,
                'error' => $e->getMessage()
            ];
        } finally {
            clearstatcache();
        };
    }

    /**
     * Creates a directory recursively, considering file system permissions.
     *
     * @param string      $directory Path of the directory to be created.
     * @param string|null $copy      Directory to be used as a reference for system permissions (optional).
     */
    private function makeFolder(string $directory, ?string $copy = null) {
        PHP_OS !== 'WINNT' && $copy !== null ?
            mkdir($directory, fileperms($copy), true) :
            mkdir($directory, true);
    }

    /**
     * Transfers the contents of a folder to a specified destination.
     */
    private function transferFolder(): void {
        list($newDirectoryOutput, $newName, $index) = $this->uniqueDirectory($this->directoryOutput);

        if ($index > 0) {
            if ($this->mergeMode === null){
                throw new Exception('There is already a folder directory with the same name at the destination.');
            } elseif ($this->mergeMode === 'both') {
                $this->directoryOutput = $newDirectoryOutput;
                $this->makeFolder($this->directoryOutput, $this->directoryInput);
            };
        } else {
            $this->makeFolder($this->directoryOutput, $this->directoryInput);
        };

        $recursiveIterator = $this->recursiveSearch(false);

        foreach ($recursiveIterator as $directory) {
            $destiny = $this->directoryOutput . DIRECTORY_SEPARATOR . $recursiveIterator->getSubPathname();
            $realDirectory = $directory->getRealPath();

            if ($directory->isDir()) {
                if (!is_dir($destiny)) {
                    $this->makeFolder($destiny, $realDirectory);
                };

                continue;
            };

            $this->transfer($realDirectory, $destiny);
        };

        if ($this->action === 'move') $this->cleanSubfolders($this->directoryInput);
    }

    /**
     * Deletes a folder and its contents.
     */
    private function delete(): void {
        if (!$this->isDir) {
            if(@unlink($this->directoryInput)) $this->storage[] = [
                'directory' => $this->directoryInput
            ];
            return;
        };

        foreach ($this->recursiveSearch(true) as $directory) {
            $realDirectory = $directory->getRealPath();

            $result = $directory->isDir() ?
                @rmdir($realDirectory)    :
                @unlink($realDirectory)   ;

            if (!$result) $this->storage[] = [
                'directory' => $realDirectory
            ];
        };

        if (!@rmdir($this->directoryInput)) $this->storage[] = [
            'directory' => $this->directoryInput
        ];
    }
};
?>
