import React, {useState, useContext} from "react";
import {Alert, Button, Fieldset, TextInput, Card, Center, Container, Grid, Space, Text, Title} from '@mantine/core';
import {InfoCircle, CCircle, Hash} from "react-bootstrap-icons";
import {useNavigate} from 'react-router-dom';
// import useAuth from "../../Hooks/useAuth.jsx";

export function Login() {
    const [error, setError] = useState('')
    const [name, setName] = useState('')
    const [phone, setPhone] = useState('')
    const [email, setEmail] = useState('')
    const [loading, setLoading] = useState(false)
    const navigate = useNavigate();
    // const { login, verifyEmail } = useAuth();


    const onChange = (e) => {
        const {name, value} = e.target
        if (name === 'email') {
            setEmail(value);
        } else if (name === 'code') {
            setCode(value);
        }
    }

    const onLogin = () => {
        // handleNext()
        setLoading(true)
        navigate('/dashboard')
        // login(name, email).then((res) => {
        //     setLoading(false)
        //     setError('')
        //
        //     // user has been cached within 30 minutes, allow entry without verification step
        //     if(res === 'pass'){
        //         navigate('/home')
        //     } else {
        //         // handleNext()
        //     }
        // }).catch((err) => {
        //     setLoading(false)
        //     setError(err)
        //     console.log('user has been rejected ', err)
        // })

    }

    const disclaimer = () => {
        return (
            <div>
                <Text fw={500} c="dimmed">Terms of Usage</Text>
                <Space h="sm"/>
                <Text size="sm"> Disclaimer - Descriptive text here
                </Text>
                <div>
                    <Center>
                        <Button
                            test="1"
                            mt="md"
                            radius="md"
                            // onClick={handleNext}
                            variant="filled"
                            color="rgba( 140, 21, 21)"
                            style={{width: '120px'}}
                        >
                            Continue
                        </Button>
                    </Center>
                </div>
            </div>
        )
    }

    const loginView = () => {
        return (
            <div>
                {error &&
                    <Alert variant="light" color="red" radius="md" title="Error">{error}</Alert>
                }
                <Space h="sm"/>
                <Fieldset legend="Access your intake dashboard">
                    <TextInput name="Email" onChange={onChange} label="Email" placeholder="Email ..."/>
                    <TextInput name="Code" onChange={onChange} label="Code" placeholder="Code ..." mt="md"/>
                    <Center>
                        <Button
                            test="1"
                            mt="md"
                            radius="md"
                            onClick={onLogin}
                            variant="filled"
                            color="rgba( 140, 21, 21)"
                            style={{width: '120px'}}
                            // loading={loading}
                        >
                            Verify
                        </Button>
                    </Center>
                </Fieldset>
                <Space h="sm"/>
            </div>
        )
    }

    return (
        <div style={{justifyContent: 'center'}} className="content">
            <Container>
                <Card shadow="lg" padding="lg" radius="md" withBorder style={{minWidth: '800px'}}>
                    <Title order={3}>Welcome to the Intake Dashboard!</Title>
                    <Space h="sm"/>
                    <Grid>
                        <Grid.Col span={12}>
                            {loginView()}
                        </Grid.Col>
                        {/*<Grid.Col span={5}>*/}
                        {/*    <Center>*/}
                        {/*        <div className="Splash"></div>*/}
                        {/*    </Center>*/}
                        {/*</Grid.Col>*/}
                    </Grid>
                    <Center>
                        <Text size="xs" c="dimmed">
                            <span style={{verticalAlign: 'middle'}}>
                                <CCircle/> Stanford Medicine
                            </span>
                        </Text>
                    </Center>
                </Card>
            </Container>
        </div>
    );
}
