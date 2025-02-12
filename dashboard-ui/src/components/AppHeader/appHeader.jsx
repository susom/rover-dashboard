import React from 'react';
import { Group, Image } from '@mantine/core';

export function AppHeader() {
    return (
            <Group h="100%" px="md" justify="space-between" w="100%">
                <Image
                    src="https://storage.googleapis.com/group-chat-therapy/stanford-logo.svg"
                    h={30}
                    w="auto"
                    fit="contain"
                    alt="stanford_image"
                />
                <p>Prototype</p>
            </Group>

    );
}
