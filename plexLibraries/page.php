<?php
    $GLOBALS['organizrPages'][] = 'PlexLibraries';
    function get_page_plex_libraries($Organizr)
    {
        if ((!$Organizr->hasDB())) {
            return false;
        }
        /*
         * Take this out if you dont want to be for admin only
         */
        if (!$Organizr->qualifyRequest(1, true)) {
            return false;
        }
        return '
        <div class="panel bg-org panel-info plexLibrariesPagePanel" id="plexLibraries-area">
        <div class="panel-heading">
            <span lang="en">Customise Plex Libraries</span>
        </div>
        <div class="panel-body">
            
            <div id="plexLibrariesTable">
                <div class="white-box m-b-0">
                    <h2 class="text-center loadingPlexLibraries" lang="en"><i class="fa fa-spin fa-spinner"></i></h2>
                    <div class="row">
                        <div class="col-lg-12">
                            <select class="form-control" name="plexUsers" id="plexUsers" style="display:none">
                                <option value="">Choose a User</option>
                            </select><br>
                        </div>
                    </div>
                    <div class="table-responsive plexLibrariesTableList hidden" id="plexLibrariesTableList">
                        <table class="table color-bordered-table purple-bordered-table text-left">
                            <thead>
                                <tr>
                                    <th width="20">Type</th>
                                    <th>Name</th>
                                    <th width="20">Action</th>
                                </tr>
                            </thead>
                            <tbody id="plexLibraries"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>plexLibrariesPluginLoadShares();</script>
        ';
    }
