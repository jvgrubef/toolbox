<?php
class FileManagerLinux {

    private $directoryInput;
    private $directoryOutput;
    private $action;
    private $path;
    private $root;
    private $force = false;
    private $storage = [];

    public function __construct(string $root = '/tmp'){
        $this->root = $root;
    }

    private function basePath(string $in = '') {
        $in = $this->inFilter($in);

        $path = realpath($this->root . DIRECTORY_SEPARATOR . $in);

        if ($path === false)                      throw new Exception('The input directory does not exist');
        if (strpos($path, $this->root) === false) throw new Exception('Path not allowed');

        return $path;
    }

    public function search(string $in, string $query, string $sortType = 'name', string $order = 'asc'): array {
        $this->directoryInput = $this->basePath($in);

        $recursiveIterator = $this->recursiveSearch(false);

        foreach ($recursiveIterator as $directory) {
            $realDirectory = $directory->getRealPath();
            
            $info = pathinfo($realDirectory);
            if(stripos($info['filename'], $query) !== false || stripos($info['basename'], $query) !== false) {
                $this->template($directory);
            };
        };

        $this->sortResult($sortType, $order);
        return $this->storage;
    }

    public function explorer(string $in, string $sortType = 'name', string $order = 'asc'): array {
        
        $this->directoryInput = $this->basePath($in);

        if (!$this->directoryInput) {
            throw new Exception("The input directory does not exist");
        };

        if(is_file($this->directoryInput) || is_link($this->directoryInput)){
            throw new Exception("The input directory is not a folder"); 
        };

        $recursiveIterator = $this->recursiveSearch(false, false);

        foreach ($recursiveIterator as $directory) $this->template($directory);

        $this->sortResult($sortType, $order);
        $this->storage['path'] = $this->directoryInput;

        return $this->storage;
    }

    public function execute(string $action, string $in, ?string $out = null, bool $force = false): array {
        if (!in_array($action, ['copy', 'move', 'delete'])) {
            throw new Exception("Action not recognized, use copy, move, or delete");
        };

        $this->action          = $action;
        $this->force           = $force;
        $this->directoryInput  = $this->basePath($in);
        $this->directoryOutput = $out;

        $isFile = is_file($this->directoryInput) || is_link($this->directoryInput);

        if($this->action === 'delete'){
 
            if ($isFile) {
                if (!@unlink($this->directoryInput)) {
                    $this->storage[] = [
                        'in'  => $this->directoryInput,
                        'act' => $this->action
                    ];
                };
            } else {
                $this->deleteFolder();
            };
        } else {
            if(!is_readable($this->directoryInput)) {
                throw new Exception("The input directory not readable");
            };

            $testDirectoryOutputDir = @dirname($this->directoryOutput);
            $testDirectoryOutputReal = $this->basePath($testDirectoryOutputDir);

            if (!$testDirectoryOutputDir) {
                throw new Exception("The target directory does not exist");
            };

            if(!is_writable($testDirectoryOutputReal)) {
                throw new Exception("The target directory not writable");
            };

            $this->directoryOutput = $testDirectoryOutputReal . DIRECTORY_SEPARATOR . $this->inFilter(basename($this->directoryOutput));

            $isFile ? 
                $this->transfer()       :
                $this->transferFolder() ;
            if (!$isFile && empty($this->storage) && $this->action === 'move') {
                $this->deleteFolder();
            };
        };
        
        return $this->storage;
    }

    private function template(object $directory) {
        $realDirectory = $directory->getRealPath();
        $fakeDirectory = trim(substr($realDirectory, strlen($this->directoryInput)), '/');

        $permissions = fileperms($realDirectory);
        $permissions = sprintf('%o', $permissions);
        $permissions = substr($permissions, -4);

        $created  = filectime($realDirectory);
        $modified = filemtime($realDirectory);

        $base = [
            'name'     => basename($realDirectory),
            'path'     => $fakeDirectory,
            'time'     => [
                'created'  => [
                    'timestamp' => $created, 
                    'formatted' => date('Y-m-d H:i:s', $created)
                ],
                'modified' => [
                    'timestamp' => $modified, 
                    'formatted' => date('Y-m-d H:i:s', $modified)
                ]
            ],
            'info'     => [
                'permissions' => $permissions,
                'owner' => posix_getpwuid(fileowner($realDirectory))['name'],
                'group' => posix_getgrgid(filegroup($realDirectory))['name']
            ],
            'readable' => is_readable($realDirectory),
            'writable' => is_writable($realDirectory)
        ];

        if ($directory->isDir()) {
            list($size, $files, $folders) = $this->infoFolder($realDirectory);

            $base['info']['type'] = 'directory';

            $base['info']['size'] = [
                'bytes'     => $size,
                'formatted' => $this->formatBytes($size),
            ];

            $base['info']['subfiles']   = $files;
            $base['info']['subfolders'] = $folders;

            $this->storage['folders'][]  = $base;
        }  else {
            $size = filesize($realDirectory);

            $base['info']['type'] = @mime_content_type($realDirectory);

            $base['info']['size'] = [
                'bytes'     => $size,
                'formatted' => $this->formatBytes($size),
            ];

            $this->storage['files'][] = $base;
        };
    }

    private function sortResult(string $sortType, string $order){
        if (!in_array($order, ['asc', 'desc'])) {
            throw new Exception('Invalid order type. Available options: "asc", "desc".');
        };
        
        $sorting = [
            'name' => function ($a, $b) use ($order) {
                $result = strcmp($a["name"], $b["name"]);
                return ($order == 'desc') ? -$result : $result;
            },
            'type' => function ($a, $b) use ($order) {
                $result = strcmp($a['info']["type"], $b['info']["type"]);
                return ($order == 'desc') ? -$result : $result;
            },
            'size' => function ($a, $b) use ($order) {
                $result = $a["info"]['size']['bytes'] - $b["info"]['size']['bytes'];
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

    private function formatBytes(int $bytes, int $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
    
        $bytes /= pow(1024, $pow);
    
        return round($bytes, $precision) . $units[$pow];
    }

    private function infoFolder(?string $directory = null) {
        $directory = $directory ?? $this->directoryInput;
        $recursiveIterator = $this->recursiveSearch(false, true, $directory);
        
        $size = $files = $folders = 0;

        foreach ($recursiveIterator as $directory) {
            $realDirectory = $directory->getRealPath();

            if($directory->isDir()) {
                ++$folders;
                continue;
            };

            $size += filesize($realDirectory);
            ++$files;
        };

        return [$size, $files, $folders];
    }

    private function inFilter(string $in = '') {
        $in = trim($in, '/');
        return in_array($in, ['.', '..']) ? '' : $in;
    }

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

    private function transferFolder(): void {
        if (!is_dir($this->directoryOutput)) {
            mkdir($this->directoryOutput, fileperms($this->directoryInput), true);
        };

        $recursiveIterator = $this->recursiveSearch(false);

        foreach ($recursiveIterator as $directory) {
            $destiny = $this->directoryOutput . DIRECTORY_SEPARATOR . $recursiveIterator->getSubPathname();
            $realDirectory = $directory->getRealPath();

            if ($directory->isDir()) {
                if(!is_dir($destiny)) {
                    mkdir($destiny, fileperms($realDirectory), true);
                }

                continue;
            };

            $this->transfer($directory->getRealPath(), $destiny);
        };
    }
    
    private function deleteFolder(): void {
        $recursiveIterator = $this->recursiveSearch(true);

        foreach($recursiveIterator as $directory) {
            $realDirectory = $directory->getRealPath();

            if ($directory->isDir()){
                $result = @rmdir($realDirectory);
            } else {
                $result = @unlink($realDirectory);
            };

            if (!$result) {
                $this->storage[] = [
                    'in'  => $realDirectory,
                    'act' => $this->action
                ];
            };
        };

        if(!rmdir($this->directoryInput)) {
            $this->storage[] = [
                'in'  => $this->directoryInput,
                'act' => $this->action
            ];
        };
    }

    private function transfer(?string $directory = null, ?string $destiny = null) : void {
        $directory = $directory ?? $this->directoryInput;
        $destiny   = $destiny   ?? $this->directoryOutput;

        if(file_exists($destiny) || is_link($destiny)){

            if($this->force) {
                if (!@unlink($destiny)) {
                    $this->storage[] = [
                        'in'  => $directory,
                        'out' => $destiny,
                        'act' => 'unlink-' . $this->action
                    ];
                    return;
                };
            } else {
                $this->storage[] = [
                    'in'  => $directory,
                    'out' => $destiny,
                    'act' => 'exist-' . $this->action
                ];
                return;
            };
        };

        if (is_link($directory)) {
            if (!@symlink(readlink($directory), $destiny)) {
                $this->storage[] = [
                    'in'  => $directory,
                    'out' => $destiny,
                    'act' => 'symbolic-' . $this->action
                ];
            }
            return;
        } else {
            $result = copy($directory, $destiny);
        };

        chmod($destiny, fileperms($directory));

        if ($result && $this->action === 'move') {
            unlink($directory);
        };

        if (!$result) {
            $this->storage[] = [
                'in'  => $directory,
                'out' => $destiny,
                'act' => $this->action
            ];
        };
    }
};
?>