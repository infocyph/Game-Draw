<?php

namespace Infocyph\Draw;

use Exception;
use SplFileObject;

class GrandDraw
{
    private SplFileObject $userListFilePath;
    private array $items;
    private array $selected = [];

    /**
     * Sets the file path for the user list.
     *
     * @param string $userListFilePath The file path for the user list.
     * @return GrandDraw Returns the instance.
     * @throws Exception If the file does not exist.
     */
    public function setUserListFilePath(string $userListFilePath): GrandDraw
    {
        if (!is_readable($userListFilePath)) {
            throw new Exception('File not found or not readable');
        }
        $this->userListFilePath = new SplFileObject($userListFilePath);
        $this->userListFilePath->setFlags(SplFileObject::READ_CSV);
        return $this;
    }

    /**
     * Sets the items for the BucketDraw object.
     *
     * @param array $items The array of items to be set.
     * @return GrandDraw The updated object.
     */
    public function setItems(array $items): GrandDraw
    {
        $this->items = $items;
        return $this;
    }

    /**
     * Retrieves the winners of the draw.
     *
     * @param int $retryCount The number of times to retry drawing a unique winner (default: 10)
     * @return array The array containing the selected winners per item.
     * @throws Exception
     */
    public function getWinners(int $retryCount = 10): array
    {
        $line = $this->getLineCount($this->userListFilePath->getRealPath()) + 1;
        $selectedWinners = [];
        foreach ($this->items as $item => $count) {
            $selectedWinners[$item] = $this->draw($count, $line, $retryCount);
        }
        return $selectedWinners;
    }

    /**
     * Draws a specified number of winners from a user list.
     *
     * @param int $pickCount The number of winners to pick.
     * @param int $lineCount The total number of lines in the user list.
     * @param int $retryCount The maximum number of retries before giving up.
     * @return array An array of the selected winners' IDs.
     * @throws Exception
     */
    private function draw(int $pickCount, int $lineCount, int $retryCount): array
    {
        $failCount = 0;
        $seekMax = $lineCount - 1;
        $winners = [];
        $pickCount = min($pickCount, $lineCount - count($this->selected));
        $pickedCount = 0;

        while ($pickedCount < $pickCount && $failCount < $retryCount) {
            $line = random_int(0, $seekMax);
            if (isset($this->selected[$line])) {
                $failCount++;
                continue;
            }
            $this->userListFilePath->seek($line);
            $id = $this->userListFilePath->fgetcsv()[0];

            if ($id && !in_array($id, $this->selected, true)) {
                $this->selected[$line] = $winners[] = $id;
                $pickedCount++;
                $failCount = 0;
                continue;
            }
            $failCount++;
        }

        return $winners;
    }


    /**
     * Retrieves the line count of a given file.
     *
     * @param string $filePath The path of the file to get the line count for.
     * @return int The total number of lines in the file.
     * @throws Exception If there is an error retrieving the line count.
     */
    private function getLineCount(string $filePath): int
    {
        $command = escapeshellarg($filePath);

        match (PHP_OS_FAMILY === 'Windows' && !getenv('SHELL')) {
            true => exec("type $command | find /v /c \"\"", $lineCount, $returnVar),
            default => exec("wc -l $command", $lineCount, $returnVar),
        };

        if ($returnVar !== 0 || empty($lineCount)) {
            throw new Exception("Error retrieving line count for file: $filePath");
        }

        return (int)strtok($lineCount[0], " ");
    }
}
