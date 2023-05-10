<?php

    namespace App\Command;

    use \Pimcore\Model\DataObject;
    use Pimcore\Console\AbstractCommand;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    class DataImportCommand extends AbstractCommand {

        protected function configure() {
           $this
               ->setName('data:import:csv')
               ->setDescription('Import command for csv file')
               ->addArgument('File', InputArgument::REQUIRED, 'which file should be imported')
               ->addArgument('DataObject', InputArgument::REQUIRED, 'which object shoud be filled');
        }

        protected function execute(InputInterface $input, OutputInterface $output): int {
            $file = $input->getArgument('File');
            $objectType = $input->getArgument('DataObject');

            if(!in_array($objectType, ['Club','Player'])) {
                $output->writeln('<error>  Type of DataObject should be "Club" or "Player"  </error>');
                return Command::FAILURE;
            }

            $path = __DIR__ . "/../../" . $file;
            if(!file_exists($path)) {
                $output->writeln('<error>  File "' . $file . '" ' . $path . ' not exists!  </error>');
                return Command::FAILURE;
            }

            $fileData = fopen($path, "r");

            switch($objectType) {
                case 'Club':
                    $this->parseClubDataObject($fileData);
                    break;
                case 'Player':
                    $this->parsePlayerDataObject($fileData);
                    break;
                default:
                    $output->writeln('<error>  No parsing  </error>');
                    fclose($fileData);
                    return Command::FAILURE;
            }
            fclose($fileData);

            return Command::SUCCESS;
        }

        private function parseClubDataObject($fileHandle): void {
            while(($data = fgetcsv($fileHandle, 1000, ",")) !== FALSE) {
                if(count($data) == 0) {
                    break;
                }
                $this->createNewClubDataObject($data);
            }
        }

        private function parsePlayerDataObject($fileHandle): void {
            while(($data = fgetcsv($fileHandle, 1000, ",")) !== FALSE) {
                if(count($data) == 0) {
                    break;
                }
                $this->createNewPlayerDataObject($data);
            }
        }

        private function createNewClubDataObject($data): void {
            $slugified_club_name = $this->slugify($data[0]);
            $image_extension = pathinfo($data[1], PATHINFO_EXTENSION);

            // create Asset for image
            $newAsset = new \Pimcore\Model\Asset\Image();
            $newAsset->setFilename("{$slugified_club_name}-logo.{$image_extension}");
            $newAsset->setData(file_get_contents($data[1]));
            $newAsset->setParent(\Pimcore\Model\Asset::getByPath("/"));
            $newAsset->save(["versionNote" => "automatic uploaded"]);

            $uid = uniqid();

            // create DataObject
            $object = new DataObject\Club();
            $object->setKey(\Pimcore\Model\Element\Service::getValidKey("{$data[0]} (#{$uid})", 'object'));
            $object->setParentId(1);
            $object->setPublished(true);
            $object->setClubname($data[0]);
            $object->setClubid($slugified_club_name);
            $object->setLogo($newAsset);
            $object->setTrainer($data[2]);
            $object->setLocation($data[3]);
            $object->setLatitude(+$data[4]);
            $object->setLongitude(+$data[5]);
            $object->setFounding(+$data[6]);
            $object->save();
        }

        private function createNewPlayerDataObject($data): void {
            $slugified_club_name = $this->slugify($data[0]);

            $club = new DataObject\Club\Listing();
            $club->setCondition("clubid LIKE ?", "%{$slugified_club_name}%");
            $clubData = $club->load();

            $uid = uniqid();

            // create DataObject
            $object = new DataObject\Player();
            $object->setKey(\Pimcore\Model\Element\Service::getValidKey("{$data[1]} {$data[2]} (#{$uid})", 'object'));
            $object->setParentId(1);
            $object->setPublished(true);
            $object->setClub($clubData);
            $object->setFirstname($data[1]);
            $object->setLastname($data[2]);
            $object->setAge(+$data[3]);
            $object->setPlayernumber(+$data[4]);
            $object->setPosition($data[5]);
            $object->save();

            $playerlist = $clubData[0]->getPlayers();
            $playerlist[] = $object;
            $clubData[0]->setPlayers($playerlist);
            $clubData[0]->save();
        }

        private function slugify($text, string $divider = '-')
        {
            $text = preg_replace('~[^\pL\d]+~u', $divider, $text);
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
            $text = preg_replace('~[^-\w]+~', '', $text);
            $text = trim($text, $divider);
            $text = preg_replace('~-+~', $divider, $text);
            $text = strtolower($text);

            if (empty($text)) {
                return 'n-a';
            }

            return $text;
        }
    }