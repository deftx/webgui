<?PHP
/* Copyright 2005-2017, Lime Technology
 * Copyright 2012-2017, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
$docroot = $docroot ?: $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Markdown.php";
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";

$system  = $_GET['system'] ?? false;
$branch  = $_GET['branch'] ?? false;
$audit   = $_GET['audit'] ?? false;
$empty   = true;
$builtin = ['unRAIDServer'];
$https   = ['stable' => 'https://raw.github.com/limetech/\&name;/master/\&name;.plg',
            'next'   => 'https://s3.amazonaws.com/dnld.lime-technology.com/\&category;/\&name;.plg'];

foreach (glob("/var/log/plugins/*.plg",GLOB_NOSORT) as $plugin_link) {
//only consider symlinks
  $plugin_file = @readlink($plugin_link);
  if ($plugin_file === false) continue;
//plugin name
  $name = plugin('name',$plugin_file) ?: basename($plugin_file,".plg");
  $custom = in_array($name,$builtin);
//switch between system and custom plugins
  if (($system && !$custom) || (!$system && $custom)) continue;
//forced plugin check?
  $checked = $audit ? check_plugin(basename($plugin_file)) : true;
//OS update?
  $os = $system && $name==$builtin[0];
  $toggle = false;
//toggle stable/next release?
  if ($os && $branch) {
    $toggle = plugin('version',$plugin_file);
    $cat = strpos($toggle,'rc')!==false ? 'stable' : 'next';
    $tmp_plg = "$name-.plg";
    $tmp_file = "/var/tmp/$name.plg";
    copy($plugin_file,$tmp_file);
    exec("sed -ri 's|^(<!ENTITY category).*|\\1 \"{$cat}\">|' $tmp_file");
    exec("sed -ri 's|^(<!ENTITY pluginURL).*|\\1 \"{$https[$branch]}\">|' $tmp_file");
    symlink($tmp_file,"/var/log/plugins/$tmp_plg");
    if (check_plugin($tmp_plg)) {
      copy("/tmp/plugins/$tmp_plg",$tmp_file);
      $plugin_file = $tmp_file;
    }
  }
//link/icon
  $icon = icon($name);
  if ($launch = plugin('launch',$plugin_file))
    $link = "<a href='/$launch'><img src='/$icon' class='list'></a>";
  else
    $link = "<img src='/$icon' class='list'>";
//description
  $readme = "plugins/{$name}/README.md";
  if (file_exists($readme))
    $desc = Markdown(file_get_contents($readme));
  else
    $desc = Markdown("**{$name}**");
//author
  $author = plugin('author',$plugin_file) ?: "anonymous";
//version
  $version = plugin('version',$plugin_file) ?: "unknown";
//category
  $cat = strpos($version,'rc')!==false ? 'next' : 'stable';
//status
  $status = 'unknown';
  $changes_file = $plugin_file;
  $url = plugin('pluginURL',$plugin_file);
  if ($url !== false) {
    $filename = "/tmp/plugins/".(($os && $branch) ? $tmp_plg : basename($url));
    if ($checked && file_exists($filename)) {
      if ($toggle && $toggle != $version) {
        $status = make_link('install',$plugin_file,'forced');
      } else {
        $latest = plugin('version',$filename);
        if (strcmp($latest,$version) > 0) {
          $version .= "<br><span class='red-text'>{$latest}</span>";
          $status = make_link("update",basename($plugin_file));
          $changes_file = $filename;
        } else {
          //status is considered outdated when older than 1 day
          $status = filectime($filename) > (time()-86400) ? 'up-to-date' : 'need check';
        }
      }
    }
  }
  $changes = plugin('changes',$changes_file);
  if ($changes !== false) {
    $txtfile = "/tmp/plugins/".basename($plugin_file,'.plg').".txt";
    file_put_contents($txtfile,$changes);
    $version .= "&nbsp;<a href='#' title='View Release Notes' onclick=\"openBox('/plugins/dynamix.plugin.manager/include/ShowChanges.php?file=".urlencode($txtfile)."','Release Notes',600,900); return false\"><img src='/webGui/images/information.png' class='icon'></a>";
  }
//write plugin information
  $empty = false;
  echo "<tr>";
  echo "<td style='vertical-align:top;width:64px'><p style='text-align:center'>{$link}</p></td>";
  echo "<td><span class='desc_readmore' style='display:block'>{$desc}</span></td>";
  echo "<td>{$author}</td>";
  echo "<td>{$version}</td>";
  echo "<td>{$status}</td>";
  echo "<td>";
  if ($system) {
    if ($os) {
      echo "<select id='change_branch' class='auto' onchange='update_table(this.value)'>";
      echo mk_options($cat,'stable');
      echo mk_options($cat,'next');
      echo "</select>";
    }
  } else {
    echo make_link('remove',basename($plugin_file));
  }
  echo "</td>";
  echo "</tr>";
//remove temporary symlink
  @unlink("/var/log/plugins/$tmp_plg");
}
if ($empty) echo "<tr><td colspan='6' style='text-align:center;padding-top:12px'><i class='fa fa-check-square-o icon'></i> No plugins installed</td><tr>";
?>
