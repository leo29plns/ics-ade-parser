<?php

namespace lightframe;

class IcsAdeParser {
    private $fecthUrl;
    private $file;

    private $from;
    private $to;
    private $location;

    private $class;
    private $group;

    private $rawEvents;
    private $parsedEvents;
    private $mergedEvents;

    public function fetchUrl(string $url): void
    {
        $url = filter_var($url, FILTER_VALIDATE_URL);
    
        if ($url !== false) {
            $ch = curl_init($url);
    
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
    
            $content = curl_exec($ch);
    
            if ($content !== false) {
                if (self::isIcsFormat($content)) {
                    $this->file = $content;
                } else {
                    throw new \Error("The content is not in ICS format.");
                }
            } else {
                throw new \Error("Error fetching content from the URL.");
            }
    
            curl_close($ch);
        } else {
            throw new \Error("The URL is not valid.");
        }
    }

    public function setFrom(int $timestamp) : void
    {
        $this->from = $timestamp;
    }

    public function setTo(int $timestamp) : void
    {
        $this->to = $timestamp;
    }

    private function verifyPeriod() : bool
    {
        $isValid = false;

        if (isset($this->from, $this->to)) {
            if ($this->from <= $this->to) {
                $isValid = true;
            } else {
                throw new \Error("The start date (from) must be before or equal to the end date (to).");
            }
        }
    
        return $isValid;
    }

    public function setClass(string $class) : void
    {
        $this->class = $class;
    }

    public function setGroup(string $group) : void
    {
        $this->group = $group;
    }

    public function setLocation(string $location) : void
    {
        $this->location = $location;
    }

    public function parseRawEvents() : ?array
    {
        if (!$this->file) {
            throw new \Error("No file to parse.");
            return null;
        }

        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $this->file, $matches);
        $events = [];

        foreach ($matches[1] as $vevent) {
            $lines = explode("\n", $vevent);

            $event = [];

            $lastKey = null;

            foreach ($lines as $line) {
                preg_match('/^([A-Z0-9_-]+)(?=:)/', $line, $key);

                if (!$key) {
                    if (isset($lastKey) && array_key_exists($lastKey, $event)) {
                        $event[$lastKey] .= stripcslashes(trim($line));
                    }
                } else {
                    $value = explode(':', stripcslashes(trim($line)), 2)[1];
                    $event[$key[1]] = $value;
                    $lastKey = $key[1];
                }
            }

            if (array_key_exists('DESCRIPTION', $event)) {
                $event['DESCRIPTION'] = preg_replace('/\(Exported.*\)/', '', $event['DESCRIPTION']);
                $event['DESCRIPTION'] = preg_replace('/\\n\\n/', '', $event['DESCRIPTION']);
            }

            $events[] = $event;
        }

        $this->rawEvents = $events;
        return $this->rawEvents;
    }

    public function parseParsedEvents() : ?array
    {
        if (!$this->rawEvents) {
            $this->parseRawEvents();

            if (!$this->rawEvents) {
                throw new \Error("No rawEvents to parse.");
                return null;
            }
        }

        $events = [];

        foreach ($this->rawEvents as $rawEventIndex => $rawEvent) {
            $events[$rawEventIndex]= [];

            if (!$this->class) {
                if (preg_match('/^(\S+)(?:\s|$)/u', $rawEvent['DESCRIPTION'], $matches)) {
                    $this->class = $matches[1];
                } else {
                    throw new \Error("Unable to parse the class automatically, is the format valid?");
                    return null;
                }
            }
            
            $groupsAndTrainer = explode("\n", $rawEvent['DESCRIPTION']);
    
            $group = [];
            $trainer = null;
            $startDate = null;
            $endDate = null;
            $location = [];
    
            foreach ($groupsAndTrainer as $item) {
                if (preg_match("/^{$this->class} - TP [A-Z]$/", $item)) {
                    $group[] = substr($item, -1);
                } else {
                    $trainer = $item;
                }
            }

            if ($group !== []) {
                $events[$rawEventIndex]['group'] = $group;

                switch (true) {
                    case count($group) === 1:
                        $events[$rawEventIndex]['groupFormat'] = 'TP';
                        break;
                    case count($group) === 2:
                        $events[$rawEventIndex]['groupFormat'] = 'TD';
                        break;
                    case count($group) > 2:
                        $events[$rawEventIndex]['groupFormat'] = 'CM';
                        break;
                }
            }

            if ($this->group) {
                if (!in_array($this->group, $group)) {
                    unset($events[$rawEventIndex]);
                    continue;
                }
            }

            if ($trainer !== null) {
                $events[$rawEventIndex]['trainer'] = $trainer;
            }
    
            if (array_key_exists('SUMMARY', $rawEvent) && preg_match('/^(R\d+\.\d+|SAÉ \d+\.\d+(?:\.[A-Z])?)\s+(.*)$/u', $rawEvent['SUMMARY'], $matches)) {
                $events[$rawEventIndex]['code'] = $matches[1];
                $events[$rawEventIndex]['title'] = preg_replace('/\s*\([A-Z]+\)|\s* - [A-Z].*/', '', $matches[2]);
            } elseif (array_key_exists('SUMMARY', $rawEvent) && preg_match('/^Autonomie (.*?) -/', $rawEvent['SUMMARY'], $matches)) {
                $events[$rawEventIndex]['code'] = $matches[1];
                $events[$rawEventIndex]['title'] = 'Autonomie';
            }

            $verifyPeriod = $this->verifyPeriod();
    
            if (array_key_exists('DTSTART', $rawEvent)) {
                $startDate = strtotime($rawEvent['DTSTART']);
                $events[$rawEventIndex]['start'] = $startDate;

                if ($verifyPeriod) {
                    if (($this->from <= $startDate && $startDate <= $this->to) === false) {
                        unset($events[$rawEventIndex]);
                        continue;
                    }
                }
            }
    
            if (array_key_exists('DTEND', $rawEvent)) {
                $endDate = strtotime($rawEvent['DTEND']);
                $events[$rawEventIndex]['end'] = $endDate;

                if ($verifyPeriod) {
                    if (!($this->from <= $endDate && $endDate <= $this->to)) {
                        unset($events[$rawEventIndex]);
                        continue;
                    }
                }
            }
    
            if (array_key_exists('LOCATION', $rawEvent)) {
                $location = explode(',', $rawEvent['LOCATION']);
                $events[$rawEventIndex]['location'] = $location;

                if ($this->location) {
                    if (!in_array($this->location, $location)) {
                        unset($events[$rawEventIndex]);
                        continue;
                    }
                }
            }
        }

        $this->parsedEvents = $events;
        return $this->parsedEvents;
    }

    public function eventsReconciliation() : ?array
    {
        if (!$this->parsedEvents) {
            $this->parseParsedEvents();

            if (!$this->parsedEvents) {
                throw new \Error("No parsedEvents to merge.");
                return null;
            }
        }

        $mergedEvents = [];

        foreach ($this->parsedEvents as $parsedEventIndex => $parsedEvents) {
            $mergedEvent['parsed'] = $this->parsedEvents[$parsedEventIndex];
            $mergedEvent['raw'] = $this->rawEvents[$parsedEventIndex];

            $mergedEvents[] = $mergedEvent;
        }

        return $mergedEvents;
    }
    
    private static function isIcsFormat(string $content) : bool
    {
        return strpos($content, "BEGIN:VCALENDAR") !== false;
    }
}