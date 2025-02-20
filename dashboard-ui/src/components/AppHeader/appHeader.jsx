import React from 'react';
import {Group, Image, Text} from '@mantine/core';

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
                <div style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'flex-end',
                    justifyContent: 'center',  // Ensures content stays centered within header height
                    height: '100%'  // Inherit parent's height
                }}>
                    <p style={{ margin: 0 }}>Prototype</p>
                    <Text c="dimmed" size="sm">Logged in as: {globalUsername}</Text>
                </div>
            </Group>

    );
}
