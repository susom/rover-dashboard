import React from "react";

import {Box, Group, Button, List, Text, Image} from '@mantine/core';
import "./error.css";

export function Error() {

    const handleClick = () => {
        const protocol = window.location.protocol; // e.g., "https:"
        const host = window.location.host;         // e.g., "example.com:3000"
        window.location.href = `${protocol}//${host}/intake-dashboard.php`;
    };

    return (
        <Box style={{ width: "100vw", height: "100vh", display: "flex", justifyContent: "center", alignItems: "center", padding: "30px" }}>
                {/* 404 Image (Left Side) */}
                <Box>
                    <Image
                        src="https://storage.googleapis.com/redcap_public_images/404.png"
                        h={400}
                        fit="contain"
                        alt="404 error"
                    />
                </Box>

                {/* Error Message (Right Side) */}
                <Box>
                    <Text size="xl" fw={700} mb="sm">
                        Oops! Something Went Wrong
                    </Text>
                    <Text mb="md">
                        We're sorry, but it looks like you've encountered an unexpected error.
                    </Text>
                    <Text mb="md" fw={700}>Possible Reasons:</Text>
                    <List mb="md">
                        <List.Item>Your session has recently expired and you might need to refresh</List.Item>
                        <List.Item>You've navigated to a page that does not exist</List.Item>
                    </List>
                    <Text mb="md">
                        Please click the button below to return to the homepage.
                    </Text>
                    <Group>
                        <Button color="blue" onClick={handleClick}>
                            Back to Safety
                        </Button>
                    </Group>
                </Box>
        </Box>
    );
}
