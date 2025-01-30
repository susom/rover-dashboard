import React, {useState, useEffect, useCallback} from "react";
// import {AppShell, Button, Group, Card, Image, Table, Text, Divider, Pagination, Title, Blockquote, List, Loader} from '@mantine/core';
// import { useDisclosure } from '@mantine/hooks';
// import {IconExternalLink, IconInfoCircle} from '@tabler/icons-react';
// import {useNavigate} from "react-router-dom";
// import { AppHeader } from '../../components/AppHeader/appHeader'; // Import the reusable header
// import { IconPlus } from '@tabler/icons-react';

import {RequestTable} from '../../components/RequestTable/requestTable.jsx';
import {Box, Button} from "@mantine/core";
import {IconExternalLink, IconPlus} from "@tabler/icons-react";

export function ChildContent({childInfo, parentInfo}) {
    const successCallback = (res) => {
        console.log(res)
    }

    const errorCallback = (err) => {
        console.log(err)
    }

    const onClick = (e) => {
        console.log(e.currentTarget.id)
        let jsmoModule;
        if (import.meta?.env?.MODE !== 'development')
            jsmoModule = ExternalModules.Stanford.IntakeDashboard;

        jsmoModule.newChildRequest({'child_id': e.currentTarget.id}, successCallback, errorCallback)
    }


    const renderEditButton = () => {
        return (
            <Button
                size="xs"
                color="green"
                component="a"
                href={childInfo?.url}
                rightSection={<IconExternalLink size={20} />}
            >
                Edit
            </Button>
        )
    }

    return (
        <div>
            <Button
                onClick = {onClick}
                rightSection={<IconPlus size={20} />}
                component="a"
                m="sm"
                id={childInfo?.child_id}
            >New Request</Button>
            <RequestTable
                caption="Test Table 1"
                columns={['Child ID', 'Submission Date', 'Survey']}
                body={[['Data 1', 'Data 2', renderEditButton()], ['Data 3', 'Data 4', renderEditButton()]]}
            />
        </div>

    )
}