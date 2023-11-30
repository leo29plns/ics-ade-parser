# ADE-ICS PHP Parser

## Overview

The ADE-ICS PHP Parser is a lightweight library from [lightframe](https://github.com/leo29plns/lightframe) designed to parse ICS calendar links from the Gustave Eiffel University ADE tool. It offers a PHP class, `IcsAdeParser`, providing methods to customize requests and retrieve parsed data.

## Class: `IcsAdeParser`

### Methods

#### `fetchUrl(string $url): void`

Fetches content from the provided URL and validates it as ICS format.

#### `setFrom(int $timestamp): void`

Sets the start timestamp to filter events from a specific date.

#### `setTo(int $timestamp): void`

Sets the end timestamp to filter events until a specific date.

#### `setClass(string $class): void`

Sets the class for parsing events.

#### `setGroup(string $group): void`

Sets the group to filter events.

#### `setLocation(string $location): void`

Sets the location to filter events.

#### `parseRawEvents(): ?array`

Parses raw events from the fetched content.

#### `parseParsedEvents(): ?array`

Parses and filters events based on class, group, and location.

#### `eventsReconciliation(): ?array`

Merges parsed events with their raw counterparts.

### Static Method

#### `isIcsFormat(string $content): bool`

Checks if the content is in ICS format.

## License

This project is under the MIT License.
