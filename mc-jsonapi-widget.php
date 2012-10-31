<?php
/*
Plugin Name: Minecraft JSONAPI Widget
Plugin URI: http://dreckbuddler.de/mc-jsonapi-widget
Description: Display information about a minecraft server.
Version: 1.0
Author: Sven Ludwig - me@muhgatus.de
Author URI: http://blog.r4w.de/
License: GPLv2
*/

/*
 * Minecraft JSONAPI Widget
 * Copyright (C) 2012 Sven Ludwig - me@muhgatus.de
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */


define('MC_JSONAPI_WIDGET_VERSION', '1.0');
define('MC_JSONAPI_WIDGET_DIR', plugin_dir_path(__FILE__));
$mc_jsonapi_file = dirname(__FILE__) . 'mc-jsonapi-widget\.php';
define('MC_JSONAPI_WIDGET_URI', plugin_dir_url($mc_jsonapi_file));

if (!class_exists('JSONAPI')) {
  require "jsonapi.class.php";
}

if (!class_exists('JG_Cache')) {
  require "JG_Cache.php";
}

if (!class_exists('McJSONapiWidget')) {
  /* load translations */
  load_plugin_textdomain( 'mc-jsonapi-widget', true, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
  /* register widget */
  add_action( 'widgets_init', create_function( '', 'register_widget("McJSONapiWidget");' ) );

  class McJSONapiWidget extends WP_Widget
  {
    function McJSONapiWidget() {
      $widget_ops = array( 
        'classname' => 'McJSONapiWidget', 
        'description' => __('Display information about a minecraft server.','mc-jsonapi-widget') 
      );
      $this->WP_Widget( 'McJSONapiWidget', __('Minecraft JSONAPI Widget','mc-jsonapi-widget') , $widget_ops );
      
  /*		if ( is_active_widget(false, false, $this->id_base) ) {
        add_action( 'wp_head', array(&$this, 'add_javascript'), 0 );
      } */
      $this->jsonapi=null;
      $this->querycache=new JG_Cache(MC_JSONAPI_WIDGET_DIR.'cache');
    }
    
    public function add_javascript() {
  /*		wp_enqueue_script('jquery');
      wp_register_script( 'mc-jsonapi-query-js', MC_JSONAPI_WIDGET_URI.'/mc-jsonapi-widget.js', array('jquery'), '1.0', true );
      wp_enqueue_script('mc-jsonapi-query-js'); */
    }

    public function jsonapi($method) {
      $arraykey=null;

      $splitpos=strpos($method,'.');
      if ($splitpos !== false) {
        $arraykey=substr($method,$splitpos+1);
        $method=substr($method,0,$splitpos);
      }

      $front_trim=null;
      $splitpos=strpos($method,'+');
      if ($splitpos !== false) {
        $front_trim=intval(substr($method,$splitpos+1));
        $method=substr($method,0,$splitpos);
      }

      $rear_trim=null;
      $splitpos=strpos($method,'-');
      if ($splitpos !== false) {
        $rear_trim=intval(substr($method,$splitpos+1));
        $method=substr($method,0,$splitpos);
      }

      error_log('Hm? '.$method);

      if ($this->querycache->get($method) !== FALSE) {
        $cached=$this->querycache->get($method);
        $value=$cached[1];
        if (time() - $cached[0] < 60) {
          return $value;
        }
      }

      if ($this->jsonapi == null)
        $this->jsonapi=new JSONAPI($this->mc_jsonapi_ip,$this->mc_jsonapi_port, $this->mc_jsonapi_username, $this->mc_jsonapi_password, $this->mc_jsonapi_salt);
      $result=$this->jsonapi->call($method);

      if ( array_key_exists('success', $result) ) {
        if ( gettype($result['success']) == 'array' ) {
          $arr=array();
          foreach ($result['success'] as $subarr) {
            if ( gettype($subarr) == 'array' && $arraykey != null) {
              if ($arraykey != null) $arr[]=$subarr[$arraykey];
              else $arr[]=print_r($subarr, true);
            } else {
              $arr[]=$subarr;
            }
          }
          $value=implode(', ', $arr);
        } else {
          $value=$result['success'];
        }
        if ($front_trim != null) {
          $value=substr($value, $front_trim);
        }
        if ($rear_trim != null) {
          $value=substr($value, 0, strlen($value)-$rear_trim);
        }
        $this->querycache->set($method, array(time(), $value));
        return $value;
      }
      $this->querycache->set($method, array(time(), null));
      return null;
    }
    
    function widget($args, $instance) {
      extract( $args );
      $title = apply_filters('widget_title', $instance['title'] );

      $this->mc_jsonapi_ip       = $instance['mc_jsonapi_ip'];
      $this->mc_jsonapi_port     = $instance['mc_jsonapi_port'];
      $this->mc_jsonapi_username = $instance['mc_jsonapi_username'];
      $this->mc_jsonapi_password = $instance['mc_jsonapi_password'];
      $this->mc_jsonapi_salt     = $instance['mc_jsonapi_salt'];
      $this->mc_jsonapi_output   = $instance['mc_jsonapi_output'];
      $this->mc_jsonapi_link     = $instance['mc_jsonapi_link'];
      
      echo $before_widget;
      
      if ( $title ) echo $before_title . $title . $after_title;

      echo '<div class="mc-jsonapi-widget">'."\n";
      echo '<ul>';
      if ( preg_match_all("/[{]([^}]+)[}]/", $this->mc_jsonapi_output, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE) ) {
        $offset_modificator=0;
        // error_log(print_r($matches,true));
        foreach ($matches as $json_function) {
          $replaced_len=strlen($json_function[0][0]);

          $callback = $json_function[1][0];

          $replacement=$this->jsonapi($callback);

          if ($replacement == "") $replacement = print_r($json_function, true);
          $this->mc_jsonapi_output=substr_replace($this->mc_jsonapi_output, $replacement, $json_function[0][1]+$offset_modificator, $replaced_len);
          $offset_modificator=$offset_modificator+(strlen($replacement)-$replaced_len);
        }
      }
      echo '<li class="mc-jsonapi-widget-playercount" onclick="window.location.replace(\''.$this->mc_jsonapi_link.'\')">'.$this->mc_jsonapi_output.'</li>';
      echo '</ul>';
      echo '</div>'."\n";
      
      echo $after_widget;
    }
    

    function form( $instance ) {
      $deftitle = __('Server Status','mc-jsonapi-widget');
      
      $defaults = array( 'title' => $deftitle, 'mc_jsonapi_ip' => '127.0.0.1', 'mc_jsonapi_port' => '20059', 'mc_jsonapi_username' => 'jane', 'mc_jsonapi_password' => 'doh', 'mc_jsonapi_salt' => 'salt meat', 'mc_jsonapi_output' => "{getPlayerCount}/{getPlayerLimit}\n{getServerVersion}", 'mc_jsonapi_link' => '');
      $instance = wp_parse_args( (array) $instance, $defaults ); 
      ?>
      <p>
        <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Title','mc-jsonapi-widget') ?></label>
        <input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:100%;" />
      </p>
    
      <p>
        <label for="<?php echo $this->get_field_id( 'mc_jsonapi_ip' ); ?>"><?php _e('Server IP','mc-jsonapi-widget') ?></label>
        <input id="<?php echo $this->get_field_id( 'mc_jsonapi_ip' ); ?>" name="<?php echo $this->get_field_name( 'mc_jsonapi_ip' ); ?>" value="<?php echo $instance['mc_jsonapi_ip']; ?>" style="width:100%;" />
      </p>
    
      <p>
        <label for="<?php echo $this->get_field_id( 'mc_jsonapi_port' ); ?>"><?php _e('Server Port','mc-jsonapi-widget') ?></label>
        <input id="<?php echo $this->get_field_id( 'mc_jsonapi_port' ); ?>" name="<?php echo $this->get_field_name( 'mc_jsonapi_port' ); ?>" value="<?php echo $instance['mc_jsonapi_port']; ?>" style="width:100%;" />
      </p>
    
      <p>
        <label for="<?php echo $this->get_field_id( 'mc_jsonapi_username' ); ?>"><?php _e('Username','mc-jsonapi-widget') ?></label>
        <input id="<?php echo $this->get_field_id( 'mc_jsonapi_username' ); ?>" name="<?php echo $this->get_field_name( 'mc_jsonapi_username' ); ?>" value="<?php echo $instance['mc_jsonapi_username']; ?>" style="width:100%;" />
      </p>
    
      <p>
        <label for="<?php echo $this->get_field_id( 'mc_jsonapi_password' ); ?>"><?php _e('Password','mc-jsonapi-widget') ?></label>
        <input id="<?php echo $this->get_field_id( 'mc_jsonapi_password' ); ?>" name="<?php echo $this->get_field_name( 'mc_jsonapi_password' ); ?>" value="<?php echo $instance['mc_jsonapi_password']; ?>" type="password" style="width:100%;" />
      </p>
    
      <p>
        <label for="<?php echo $this->get_field_id( 'mc_jsonapi_salt' ); ?>"><?php _e('SALT','mc-jsonapi-widget') ?></label>
        <input id="<?php echo $this->get_field_id( 'mc_jsonapi_salt' ); ?>" name="<?php echo $this->get_field_name( 'mc_jsonapi_salt' ); ?>" value="<?php echo $instance['mc_jsonapi_salt']; ?>" style="width:100%;" />
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'mc_jsonapi_output' ); ?>"><?php _e('Output','mc-jsonapi-widget') ?></label>
        <textarea class="widefat" id="<?php echo $this->get_field_id( 'mc_jsonapi_output' ); ?>" name="<?php echo $this->get_field_name( 'mc_jsonapi_output' ); ?>" rows="5"><?php echo $instance['mc_jsonapi_output']; ?></textarea>
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'mc_jsonapi_link' ); ?>"><?php _e('Redirect on click','mc-jsonapi-widget') ?></label>
        <input id="<?php echo $this->get_field_id( 'mc_jsonapi_link' ); ?>" name="<?php echo $this->get_field_name( 'mc_jsonapi_link' ); ?>" value="<?php echo $instance['mc_jsonapi_link']; ?>" style="width:100%;" />
      </p>

      <?php
    }

    function update( $new_instance, $old_instance ) {
      $instance = $old_instance;
      $instance['title'] = strip_tags( $new_instance['title'] );
      $instance['mc_jsonapi_ip'] = strip_tags( $new_instance['mc_jsonapi_ip'] );
      $instance['mc_jsonapi_port'] = strip_tags( $new_instance['mc_jsonapi_port'] );
      $instance['mc_jsonapi_username'] = strip_tags( $new_instance['mc_jsonapi_username'] );
      $instance['mc_jsonapi_password'] = strip_tags( $new_instance['mc_jsonapi_password'] );
      $instance['mc_jsonapi_salt'] = strip_tags( $new_instance['mc_jsonapi_salt'] );
      $instance['mc_jsonapi_output'] = strip_tags( $new_instance['mc_jsonapi_output'],'<p><br><img><a><b><i><span><div>');
      $instance['mc_jsonapi_link'] = strip_tags( $new_instance['mc_jsonapi_link'] );
      return $instance;
    }
  }

}
