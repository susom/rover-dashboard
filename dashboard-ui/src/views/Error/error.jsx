import React, {useState} from "react";

import {Button, Card, Grid, Group, Text, Stepper} from '@mantine/core';
import {Link} from "react-router-dom";

export function Error() {
    return (
        <div style={{width: '100vw', height: '100vh', display: 'flex', flexDirection: 'column', padding: '30px'}}>
            <Grid style={{height: '100%'}}>
                <Grid.Col span={12} style={{padding: 0}}>
                    <Card shadow="sm" p="lg" style={{height: '100%'}}>
                        <h1>Oops! Something Went Wrong</h1>
                        <p>
                            We're sorry, but it looks like you've encountered an unexpected error.
                        </p>
                        <p>
                            Please click the button below to return to the homepage.
                        </p>
                        <Link to="/">
                            Back to Safety
                        </Link>
                    </Card>
                </Grid.Col>
            </Grid>
        </div>
    );
}
