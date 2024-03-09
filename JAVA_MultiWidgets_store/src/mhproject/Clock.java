/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Classes/Class.java to edit this template
 */
package mhproject;

import java.awt.BasicStroke;
import java.awt.Color;
import java.awt.Font;
import java.awt.Graphics;
import java.awt.Graphics2D;
import java.text.SimpleDateFormat;
import java.util.Date;
import javax.swing.JFrame;
import javax.swing.JPanel;
import javax.swing.Timer;

/**
 *
 * @author mhn
 */
public class Clock extends JFrame{
    
        Clock()
        {
          setTitle("Analog Clock");
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setSize(400, 400);
        setLocationRelativeTo(null);
        ClockPanel clock = new ClockPanel();
        add(clock);
        
        // Start a timer to update the clock every second
        Timer timer = new Timer(1000, e->{
        
            clock.setCurrentTime();
            clock.repaint();
            
        });
        timer.start();
        
        // Initial clock update
        clock.setCurrentTime();
        
    }
    
    
    // Create an Inner class representing the clock panel
    private class ClockPanel extends JPanel
    {
        private int centerX;
        private int centerY;
        private int clockRadius;
        
        public ClockPanel()
        {
            setBackground(Color.gray);
        }
        
        public void setCurrentTime()
        {
            // Repaint the clock panel to update the clock hands
            repaint();
        }
        
        
        @Override
        protected void paintComponent(Graphics g)
        {
            super.paintComponent(g);
            Graphics2D g2d = (Graphics2D) g;
            
            // Calculate clock dimensions and center
            clockRadius = Math.min(getWidth(), getHeight()) / 2 - 20; // Calculate the radius of the clock, leaving a margin of 20 pixels
            centerX = getWidth() / 2; // Calculate the X-coordinate of the clock center
            centerY = getHeight() / 2; // Calculate the Y-coordinate of the clock center
            
            // Draw clock face (circle)
            g2d.setColor(new Color(50,50,50)); // Set the color for the clock face
             // Draw a filled circle representing the clock
            g2d.fillOval(centerX - clockRadius, centerY - clockRadius, 2 * clockRadius, 2 * clockRadius);
            
            // Draw hour numbers
            g2d.setFont(new Font("Arial", Font.BOLD, 14)); // Set the font for drawing hour numbers
            g2d.setColor(Color.white); // Set the color for the hour numbers
            
            for(int hour = 1; hour <= 12; hour++)
            {
                double angle = Math.toRadians(90-(360/12) * hour); // Calculate the angle for placing the hour number
                int x = (int)(centerX + clockRadius * 0.8 * Math.cos(angle)); // Calculate the X-coordinate of the hour number position
                int y = (int)(centerY - clockRadius * 0.8 * Math.sin(angle)); // Calculate the Y-coordinate of the hour number position
                g2d.drawString(Integer.toString(hour), x - 7, y + 5); // Draw the hour number with an offset
            }
            
            // Get current time
            SimpleDateFormat sdf = new SimpleDateFormat("HH:mm:ss"); // Create a date formatter for hours, minutes, and seconds
            String currentTime = sdf.format(new Date()); // Get the current time as a formatted string
            
            // Extract hours, minutes, and seconds
            int hours = Integer.parseInt(currentTime.substring(0,2)); // Extract the hours
            int minutes = Integer.parseInt(currentTime.substring(3,5)); // Extract the minutes
            int seconds = Integer.parseInt(currentTime.substring(6,8)); // Extract the seconds
            
            // Calculate rotation angles for clock hands
            double hoursAngle = Math.toRadians(90 - (360 / 12) * hours); // Calculate the angle for the hour hand
            double minutesAngle = Math.toRadians(90 - (360 / 60) * minutes); // Calculate the angle for the minutes hand
            double secondsAngle = Math.toRadians(90 - (360 / 60) * seconds); // Calculate the angle for the seconds hand
            
            // Draw clock hands
            // Draw the hour hand in yellow
            drawClockHand(g2d, centerX, centerY, clockRadius * 0.5, hoursAngle, 6, Color.yellow);
            
            // Draw the minute hand in yellow
            drawClockHand(g2d, centerX, centerY, clockRadius * 0.7, minutesAngle, 4, Color.yellow);
            
            // Draw the second hand in red
            //g2d.setColor(new Color(255, 90, 90));
            drawClockHand(g2d, centerX, centerY, clockRadius * 0.8, secondsAngle, 2, new Color(255, 90, 90));

        }
        
        
        
        private void drawClockHand(Graphics2D g2d, int x, int y, double length, 
                                   double angle, int thickness, Color color)
        {
            // Set the stroke thickness and color for drawing the clock hand
            g2d.setStroke(new BasicStroke(thickness));
            g2d.setColor(color);
            int x2 = (int)(x + length * Math.cos(angle));
            int y2 = (int)(y - length * Math.sin(angle));
            // Draw the clock hand from the center to the calculated position
            g2d.drawLine(x, y, x2, y2);
        }
        
       
        }

}
