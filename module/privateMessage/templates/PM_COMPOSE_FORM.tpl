        <div class="pmFormContainer centerText">
            <form action="{$MODULE_PAGE_URL}" method="POST" class="pmComposeForm">
                <input name="action" value="submitPm" type="hidden">
                <table class="pmFormTable">
                    <tr>
                        <td class="postblock"><label for="pmRecipient">{$RECIPIENT_LABEL}</label></td>
                        <td><input id="pmRecipient" name="recipient" type="text" class="pmRecipientInput" placeholder="{$RECIPIENT_PLACEHOLDER}" value="{$PREFILL_RECIPIENT}"></td>
                    </tr>
                    <tr>
                        <td class="postblock"><label for="pmName">{$NAME_LABEL}</label></td>
                        <td><input id="pmName" name="name" type="text" class="pmNameInput"></td>
                    </tr>
                    <tr>
                        <td class="postblock"><label for="pmSubject">{$SUBJECT_LABEL}</label></td>
                        <td><input id="pmSubject" name="subject" type="text" class="pmSubjectInput" value="{$PREFILL_SUBJECT}"></td>
                    </tr>
                    <tr>
                        <td class="postblock"><label for="pmBody">{$BODY_LABEL}</label></td>
                        <td><textarea id="pmBody" name="body" class="pmBodyInput" rows="4">{$PREFILL_BODY}</textarea></td>
                    </tr>
                </table>
                <input type="submit" value="{$SEND_LABEL}" class="pmSendBtn">
            </form>
        </div>