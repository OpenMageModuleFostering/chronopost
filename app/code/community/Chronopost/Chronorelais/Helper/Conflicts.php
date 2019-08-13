<?php

class Chronopost_Chronorelais_Helper_Conflicts extends Mage_Core_Helper_Abstract {

    const DEBUG = false;

    var $rewrites;

    var $whiteList = array("Mage_Adminhtml_Controller_Action", "Mage_Core_Controller_Front_Action");

    public function checkForConflicts() {
        $this->rewrites = array(
                "chrono" => array(
                    "blocks" => array(),
                    "helpers" => array(),
                    "models" => array(),
                    "controllers" => array(),
                ),
                "other" => array(
                    "blocks" => array(),
                    "helpers" => array(),
                    "models" => array(),
                    "controllers" => array(),
                ),
            );

        // Define pathes to check
        $mageCodeDir = Mage::getBaseDir() . DS . 'app' . DS . 'code' . DS;
        $modulePathes = array($mageCodeDir . 'local', $mageCodeDir . 'community');

        $result = "";

        // Recursively browse pathes
        foreach ($modulePathes as $modulePath) {

            if(!is_dir($modulePath)) {
                continue;
            }

            $dir = new RecursiveDirectoryIterator($modulePath, FilesystemIterator::SKIP_DOTS);
            $ite  = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);

            // Get config.xml files
            $configXmls = new RegexIterator($ite, '/^.+config\.xml$/i', RecursiveRegexIterator::GET_MATCH);

            foreach ($configXmls as $configXml) {
                if (self::DEBUG) {
                    $result .= '' . $configXml[0] . "\n";
                }

                // Parse XML to look for rewrites
                $xml = simplexml_load_file($configXml[0]);
                $rewrites = $xml->xpath('//rewrite');

                while(list( , $node) = each($rewrites)) {
                    $moduleNode = $node->xpath("..");
                    $moduleName = $moduleNode[0]->getName();

                    // Filter route rewrites
                    if ($moduleName != "global") {
                        $typeNode = $moduleNode[0]->xpath("..");
                        $typeName = $typeNode[0]->getName();

                        foreach ($node->children() as $child) {
                            $rewriteFrom = "Mage_" . str_replace(" ", "_", ucwords($moduleName . " " . substr($typeName, 0, -1) . " " . str_replace("_", " ", $child->getName())));

                            if (self::DEBUG) {
                                $result .= "Rewrite from : " . $rewriteFrom . "\n";
                                $result .= "Rewrite to : " . $child->__toString() . "\n";
                            }

                            if (strpos($child->__toString(), "Chronopost_Chronorelais") !== false) {
                                $scope = "chrono";
                            } else {
                                $scope = "other";
                            }

                            if (!isset($this->rewrites[$scope][$typeName][$rewriteFrom])) {
                                $this->rewrites[$scope][$typeName][$rewriteFrom] = array();
                            }
                            $this->rewrites[$scope][$typeName][$rewriteFrom][] = $child->__toString();
                        }
                    }
                }

                if (self::DEBUG) {
                    $result .= "\n";
                }
            }

            // Get controllers PHP files
            $controllerPhps = new RegexIterator($ite, '/^.+controllers.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

            foreach ($controllerPhps as $controllerPhp) {
                $className = "";
                $scope = "";

                $php_file = file_get_contents($controllerPhp[0]);
                $tokens = token_get_all($php_file);
                $class_token = false;
                $extends_token = false;
                foreach ($tokens as $token) {
                  if (is_array($token)) {
                    if ($token[0] == T_CLASS) {
                       $class_token = true;
                    } else if ($class_token && $token[0] == T_STRING) {
                       $className = $token[1];
                       $class_token = false;
                    } else if ($token[0] == T_EXTENDS) {
                       $extends_token = true;
                    } else if ($extends_token && $token[0] == T_STRING) {
                        if (self::DEBUG) {
                            $result .= 'File ' . $controllerPhp[0] . ":\n" .
                                "  Class " . $className . "\n" .
                                "  Extends " . $token[1] . "\n\n";
                        }

                        if (empty($scope)) {
                            if (strpos($className, "Chronopost_Chronorelais") !== false) {
                                $scope = "chrono";
                            } else {
                                $scope = "other";
                            }
                        }

                        if (!isset($this->rewrites[$scope]["controllers"][$token[1]])) {
                            $this->rewrites[$scope]["controllers"][$token[1]] = array();
                        }
                        $this->rewrites[$scope]["controllers"][$token[1]][] = $className;

                        $className = "";
                        $extends_token = false;
                    }
                  }
                }
            }
        }

        // Parse rewrites table to check for conflicts

        foreach ($this->rewrites['chrono'] as $type => $rewrites) {
            foreach ($rewrites as $from => $tos) {
                if (!in_array($from, $this->whiteList) && isset($this->rewrites['other'][$type][$from])) {
                    // Houston, we have a rewrite
                    $result .= "<p>[" . $type . "] La réécriture de la Classe <u>" . $from . "</u> par <u>" . implode(', ', $tos) . "</u> pourrait entrer en conflit avec les réécritures faites par <u>" . implode(', ', $this->rewrites['other'][$type][$from]) . "</u></p>\r\n";
                }
            }
        }

        if (empty($result)) {
            $result = "Aucun conflit n'a été detecté au niveau des controleurs, des classes de block, des helpers ou des modèles.";
        } else {
            $result = "<p>Des conflits potentiels ont été détectés :</p>" . $result . "<p>Ces résultats sont à analyser individuellement. Ils offrent des pistes à étudier et ne remplacent pas une étude approfondie du code.</p>";
        }

        return $result;
    }

}
