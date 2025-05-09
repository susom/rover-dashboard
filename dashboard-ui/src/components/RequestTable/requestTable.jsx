import React, {useState, useEffect, useCallback} from "react";
import {AppShell, Button, Group, Card, Image, Table, Text, Divider, Pagination, Title, Blockquote, List, Loader} from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
// import {IconExternalLink, IconInfoCircle} from '@tabler/icons-react';
// import {useNavigate} from "react-router-dom";
// import { AppHeader } from '../../components/AppHeader/appHeader'; // Import the reusable header
// import { IconPlus } from '@tabler/icons-react';
import "./requestTable.css";

export function RequestTable({caption, columns, body}) {
    const tableConfig = {
        caption: caption,
        head: columns,
        body: body
    }
    return (
        <div style={{ overflow: 'auto', maxHeight: '300px', minWidth: '300px' }}>
        <Table.ScrollContainer type="auto" >
                <Table
                    className="request-table"
                    stickyHeader
                    data={tableConfig}
                    striped
                />
            </Table.ScrollContainer>
        </div>
    )
}